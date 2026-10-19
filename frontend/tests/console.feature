# language: pt-BR
Funcionalidade: Grade inferior de console (output + input)
  Origem: KAN-18 (RFC aprovada; fase de desenho de UI, MVE).
  Contratos congelados: docs/KAN-18-guide.md (nomes, store, comandos, a11y).

  A grade nasce FECHADA a cada carregamento, sem persistência (sem
  localStorage). O output deriva de GET /api/issues por polling (5s):
  comentários com author !== 'user', deduplicados pela chave adaptadora do
  guia (issueId:commentId quando há id; senão autor + instante + hash do body).
  O input executa APENAS o catálogo fechado (/status, /list, /help, /clear)
  localmente contra o boardStore — sem shell, sem POST novo; comando
  desconhecido vira mensagem kind "error", nunca avaliação de input.

  Contrato congelado:
  - Mensagem: { seq, at, actor, kind: 'agent'|'system'|'command'|'error', text }.
  - `seq` é monotônico; `unread` cresce a cada append e zera com
    clearUnread()/clear().
  - pollOnce é idempotente: reler as mesmas issues não duplica mensagem.
  - O polling roda enquanto a grade está ABERTA: start() ao abrir e stop() ao
    fechar (amarrado a consoleOpen pela fundação), pausa com document.hidden.
  - start() dispara o poll imediato (KAN-124): o primeiro lote não espera 5s.
  - Todo "at" da mensagem é ISO 8601 UTC (Date#toISOString), inclusive o do
    presenter PHP com created_at "+00:00"; o cru só entra na chave de dedupe.
  - Rótulos do console em pt-BR (docs/KAN-18-guide.md, contrato de nomes).
  - Catálogo fechado: /status, /list, /help, /clear; desconhecido → 'error'.
  - /clear é ação puramente local: zera mensagens e unread, zero chamadas HTTP.
  - busy enquanto um comando executa: reenvio no mesmo instante é ignorado.
  - Atalho Ctrl+J/Cmd+J desktop-only (inerte no mobile, espelha o Ctrl+B).
  - Altura: 240px inicial; clamp 120px..60vh; duplo clique no handle restaura
    240px; setas ↑/↓ com o handle focado = ±16px.
  - A11y: role="log" + aria-live="polite" no output; botão com
    aria-label="Alternar console", aria-expanded e aria-controls="app-console";
    handle focável com aria-label="Redimensionar console"; form com
    aria-label="Entrada do console" e placeholder "Digite um comando… (/help)";
    botão "Enviar"; vazio "Nenhuma mensagem"; filtro "Filtrar por ator" com
    opção "Todas"; atividade "Nova saída"/"Ocioso"; pílula "N nova(s)".

  Mapeamento das suítes (quem mede o quê), pela ordem dos cenários abaixo:
  - Integração (node:test, tests/console.test.mjs): 3, 8, 9, 10.
  - Componentes (vitest): 1, 2, 4, 5, 7, 11 — abrir/fechar com toggle e
    atalho (inerte no mobile), indicador de atividade, clamp de altura,
    filtro do output, autoscroll travando e pílula limpando unread; pílula
    singular "1 nova" e plural "N novas" pelo mesmo spec.
  - Fundação (KAN-65) + loopback da store: 6 (start/stop com a grade).
  - Rótulos pt-BR do output e do input: componentes (vitest).
  - Coletor de erro de runtime: cypress/support/e2e.js, cenário 14; a prova
    de que ele derruba (e de que o limpo passa) está no registro do PR.
  - Harness (node:test, tests/run-e2e-port.test.mjs): cenário 15 — porta
    ocupada aborta em vez de medir outro Vite.
  - Navegador real (Cypress, cypress/e2e/console.cy.js): 12 e 13 — cenários
    marcados como "E2E" abaixo, prova na interface em execução, não no source.

  Contexto:
    Dado o usuário autenticado no board
    E a grade de console fechada por padrão

  Cenário: abrir e fechar a grade pelo botão e pelo atalho
    Quando clica no botão .console-toggle do topo
    Então a section#app-console fica visível com os painéis output e input
    E o botão passa a ter aria-expanded "true"
    Quando pressiona Ctrl+J (ou Cmd+J no macOS)
    Então a grade volta a ficar oculta
    E acionamentos repetidos mantêm o estado estável, sem travar nem duplicar

  Cenário: o atalho é inerte no mobile
    Dado um viewport menor que o breakpoint da sidebar
    Quando pressiona Ctrl+J
    Então a grade permanece fechada

  Cenário: output derivado do polling sem duplicar
    Dado issues no board com comentários de agentes e de "user"
    Quando o primeiro pollOnce roda
    Então só os comentários com author diferente de "user" viram mensagens kind "agent"
    E o actor da mensagem é o author do comentário e o text é o body
    Quando o mesmo pollOnce roda de novo sobre as mesmas issues
    Então nenhuma mensagem nova é acrescentada, por dedupe em issueId:commentId
    Quando o payload não traz id (presenter PHP: author, body, created_at)
    Então a chave é autor + instante + hash do body e a releitura não duplica
    Quando um comentário novo de agente chega no poll seguinte
    Então só essa mensagem nova é acrescentada ao fim do log

  Cenário: o filtro por ator é só de render
    Dado mensagens de dois atores no log
    Quando seleciona um ator no filtro do output
    Então só as linhas desse ator ficam visíveis
    E as mensagens dos demais atores continuam no estado, nada é apagado

  Cenário: autoscroll travável e pílula de volta ao fim
    Dado o log ancorado no fundo, com o usuário a até 24px do fim
    Quando uma mensagem nova chega
    Então o log desce automaticamente até ela
    Quando o usuário rola para cima, saindo da âncora, e outra mensagem chega
    Então o autoscroll fica travado e a pílula .console-jump mostra o unread
    Quando clica na pílula
    Então o log volta ao fundo e o unread zera

  Cenário: a pílula singular da volta ao fim
    Dado o log ancorado no fundo e o usuário rolou para cima, fora da âncora
    Quando exatamente uma mensagem nova chega
    Então a pílula .console-jump mostra "1 nova"
    Quando mais uma mensagem chega antes de voltar ao fundo
    Então a pílula passa a mostrar "2 novas"

  Cenário: start e stop acompanham a grade aberta e fechada
    Dado a grade aberta e o consoleStore em polling
    Quando a fundação fecha a grade
    Então stop() encerra o ciclo e state.polling fica "false"
    Quando a fundação abre a grade de novo
    Então start() retoma o ciclo, dispara o poll imediato e o log abre
    preenchido com os comentários de agentes, sem duplicar na releitura
    E com document.hidden nenhum poll é disparado por start()

  Cenário: indicador de atividade sinaliza chegada
    Dado o painel de output visível
    Quando uma mensagem nova chega pelo polling
    Então o indicador de atividade sinaliza a chegada e depois retorna ao repouso

  Cenário: catálogo fechado de comandos
    Dado o boardStore já carregado
    Quando digita "/help" e envia pelo form.console-input
    Então o eco kind "command" e a resposta kind "system" aparecem no log
    Quando digita "/status" e envia
    Então a resposta kind "system" resume o estado dos agentes
    Quando digita "/list" e envia
    Então a resposta kind "system" lista as issues do board carregado
    E "/clear" não entra nesse laço: ele zera o log (ver cenário próprio)
    Quando digita um comando fora do catálogo e envia
    Então aparece uma mensagem kind "error" que sugere /help e nada é executado

  Cenário: /clear é local e zera o log
    Dado mensagens no log
    Quando digita "/clear" e envia
    Então o log fica vazio, o unread volta a zero e nenhuma chamada HTTP acontece

  Cenário: busy impede reenvio duplicado
    Dado um comando em execução
    Quando um segundo comando é enviado antes do primeiro terminar
    Então o reenvio é ignorado, com apenas um eco kind "command" no log
    E busy volta a "false" depois que o comando termina

  Cenário: redimensionamento da grade
    Dado a grade aberta com a altura inicial de 240px
    Quando arrasta o handle .console-resize ou usa as setas ↑/↓ com ele focado
    Então a altura muda respeitando o clamp de 120px a 60vh
    Quando dá duplo clique no handle
    Então a altura volta a 240px

  Cenário: E2E — a grade abre no navegador real
    Dado o dev server rodando pelo harness npm run test:e2e
    Quando o Cypress abre o board e clica no .console-toggle
    Então a grade aparece e o role "log" está presente na interface em execução
    E nenhum erro de runtime ou console é observado

  Cenário: E2E — comandos digitados no navegador
    Dado a grade aberta no navegador
    Quando digita "/help" no form.console-input e envia
    Então a resposta do /help aparece renderizada no log visível
    Quando digita "/clear" e envia
    Então o log esvazia na tela
    E o mesmo fluxo passa no tema escuro e no claro

  Cenário: E2E — erro de runtime no navegador derruba o cenário
    Dado qualquer spec do Cypress que visita o board
    Quando o console do navegador registra um erro ou uma rejeição solta
    Então o afterEach do suporte reprova o cenário com as linhas capturadas
    E um cenário sem erro passa limpo (prova de mutação do coletor)

  Cenário: E2E — o harness recusa medir um servidor alheio
    Dado outro processo escutando na porta pedida em E2E_PORT
    Quando npm run test:e2e sobe o harness
    Então o harness aborta com status 1 e avisa que a porta está em uso
    E o Cypress nem chega a rodar contra o servidor alheio
