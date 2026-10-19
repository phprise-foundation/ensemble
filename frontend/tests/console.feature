#language: pt-BR
# Fonte única do aceite da grade inferior (KAN-18) — contrato congelado no
# refinamento (KAN-68, comentário #8 da QA), emendado na revisão da KAN-64.
# Nomes e forma são imutáveis:
#   store: `consoleStore` em `src/store/console.js`, comandos via `runCommand`
#   em `src/store/commands.js`; mensagem tem exatamente as chaves
#   `seq`, `at`, `kind`, `actor`, `text`; catálogo fechado
#   `/status /list /clear /help`; `/clear` é ação local (zero HTTP).
# Quem mede o quê:
#   - cenários de integração (1–7): `tests/console.test.mjs` (node:test)
#   - cenários de componente (8–11): `tests/console.spec.js` (vitest)
#   - cenários E2E (12–14): `cypress/e2e/console.cy.js` (navegador real)
# Os cenários 8–14 exigem a fundação KAN-65 (`AppConsole.vue`, toggle no topo,
# atalho Ctrl+J, clamp de altura) integrada na árvore.

Funcionalidade: Grade inferior de console
  Para acompanhar o trabalho dos agentes sem sair do board
  Como usuário autenticado do board
  Eu quero um console em grade inferior com output dos agentes e comandos predefinidos

  Contexto: fundação
    Dado que o board está carregado com a sessão ativa

  Cenário: mensagem normalizada com chaves exatas
    Quando uma mensagem entra no console
    Então ela possui exatamente as chaves "seq", "at", "kind", "actor" e "text"
    E "seq" cresce monotonicamente
    E "at" é um timestamp ISO
    E "kind" é "command", "system", "agent" ou "error"
    E um "at" explícito recebido no append é preservado

  Cenário: polling idempotente não duplica comentários dos agentes
    Dado que o endpoint de issues responde com comentários de agentes
    Quando "pollOnce" roda duas vezes sobre a mesma carga
    Então cada comentário aparece uma única vez no log
    E a chave de dedupe é "issueId:commentId" quando o comentário traz "id"
    E o fallback da chave é "issueId:autor@instante#hash do texto", com instante "created_at ?? at"
    E comentários do autor "user" não entram no log

  Cenário: os dois payloads reais de /api/issues entram sem perda nem duplicata
    Dado um payload no formato do fake backend, com "id" e "at" nos comentários
    E um payload no formato do presenter PHP, com "created_at" e sem "id"
    E dois comentários do mesmo autor na mesma issue e no mesmo instante
    Quando "pollOnce" roda duas vezes sobre cada payload
    Então os dois comentários aparecem, na ordem, uma única vez cada
    E o "at" da mensagem é o instante do comentário
    Quando um comentário novo chega
    Então ele entra no poll seguinte

  Cenário: polling tolera falha e chamadas concorrentes
    Dado que o endpoint de issues falha
    Quando "pollOnce" roda de novo
    Então a store não rejeita
    E uma única mensagem "error" aparece até o próximo poll bem-sucedido
    Quando dois "pollOnce" rodam juntos
    Então eles compartilham a mesma requisição
    Quando a aba está oculta
    Então o tick não consulta o endpoint

  Cenário: catálogo fechado de comandos
    Dado que o catálogo do console é "/status", "/list", "/clear" e "/help"
    Quando cada comando do catálogo é executado
    Então cada um responde com texto de sistema não vazio em pt-BR
    E "/status" responde mesmo com registro de agentes vazio
    Quando um comando fora do catálogo é executado
    Então a resposta tem kind "error", cita o catálogo e sugere "/help"

  Cenário: /status e /list rendem nos dois formatos de issue
    Dado que o boardStore tem issues com "summary" e "statusId"
    E issues com "title" e "status"
    Quando "/status" é executado
    Então agentes e contagem por status aparecem, sem "undefined"
    Quando "/list" é executado
    Então os itens ativos aparecem com título e status, sem "undefined"
    E itens em status final ficam de fora

  Cenário: /clear é ação puramente local
    Dado que o console tem mensagens acumuladas
    Quando "/clear" é executado
    Então o log e "unread" ficam zerados
    E nenhum eco nem resposta é gravado
    E nenhuma requisição HTTP é feita

  Cenário: busy durante o comando impede reenvio
    Dado que um comando do catálogo está em execução
    Então "busy" está marcado durante a execução do handler
    E "busy" é liberado quando o handler termina
    E o input não reenvia comando enquanto "busy" está marcado, sem eco duplicado

  Cenário: AppConsole abre e fecha com o toggle e o atalho
    Dado que a fundação AppConsole está integrada
    Quando o toggle do topo é acionado
    Então a grade inferior abre
    Quando "Ctrl+J" é pressionado
    Então a grade alterna entre aberta e fechada

  Cenário: atalho do console é inerte no mobile
    Dado que a viewport está abaixo do breakpoint mobile
    Quando "Ctrl+J" é pressionado
    Então a grade não abre no mobile

  Cenário: resize da grade respeita o clamp de altura
    Dado que a grade está aberta
    Quando o divisor é arrastado além dos limites
    Então a altura fica presa entre o mínimo e o máximo definidos

  Cenário: filtro do output, autoscroll travável e pílula de não lidas
    Dado que o output tem mensagens de agentes e de sistema
    Quando o filtro por ator é aplicado
    Então apenas as mensagens do ator escolhido ficam visíveis
    Quando o autoscroll está travado e novas mensagens chegam
    Então a posição de scroll não salta
    E a pílula de não lidas aparece e zera ao focar o console

  Cenário: E2E — toggle abre a grade e /help renderiza no log
    Dado que o navegador está aberto no board
    Quando o toggle do topo é acionado e "/help" é digitado no input
    Então a grade abre e o resultado do comando aparece no log com "role=log"

  Cenário: E2E — /clear esvazia o log no navegador
    Dado que o log tem mensagens renderizadas
    Quando "/clear" é digitado no input
    Então o log esvazia sem recarregar a página

  Cenário: E2E — role log presente nos temas escuro e claro
    Dado que o tema escuro está ativo
    Então o log do console possui "role=log" renderizado
    Quando o tema claro é ativado
    Então o log do console continua com "role=log" renderizado
