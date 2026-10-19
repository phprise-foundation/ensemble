#language: pt-BR
# Fonte única do aceite da grade inferior (KAN-18) — contrato congelado no
# refinamento (KAN-68, comentário #8 da QA). Nomes e forma são imutáveis:
#   store: `consoleStore` em `src/store/console.js`, comandos via `runCommand`
#   em `src/store/commands.js`; mensagem tem exatamente as chaves
#   `seq`, `at`, `kind`, `actor`, `text`; catálogo fechado
#   `/status /list /clear /help`; `/clear` é ação local (zero HTTP).
# Quem mede o quê:
#   - cenários de integração (1–5): `tests/console.test.mjs` (node:test)
#   - cenários de componente (6–9): `tests/console.spec.js` (vitest)
#   - cenários E2E (10–12): `cypress/e2e/console.cy.js` (navegador real)
# Os cenários 6–12 exigem a fundação KAN-65 (`AppConsole.vue`, toggle no topo,
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

  Cenário: polling idempotente não duplica comentários dos agentes
    Dado que o endpoint de issues responde com comentários de agentes
    Quando "pollOnce" roda duas vezes sobre a mesma carga
    Então cada comentário aparece uma única vez no log
    E a chave de dedupe é "issueId:commentId"
    E comentários do autor "user" não entram no log

  Cenário: catálogo fechado de comandos
    Dado que o catálogo do console é "/status", "/list", "/clear" e "/help"
    Quando cada comando do catálogo é executado
    Então cada um responde com texto de sistema não vazio
    E "/status" responde mesmo com registro de agentes vazio
    Quando um comando fora do catálogo é executado
    Então a resposta tem kind "error" e cita o catálogo

  Cenário: /clear é ação puramente local
    Dado que o console tem mensagens acumuladas
    Quando "/clear" é executado
    Então o log fica vazio de mensagens de agentes
    E nenhuma requisição HTTP é feita

  Cenário: busy durante o comando impede reenvio
    Dado que um comando do catálogo está em execução
    Então "busy" está marcado durante a execução do handler
    E "busy" é liberado quando o handler termina
    E o input não reenvia comando enquanto "busy" está marcado

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
