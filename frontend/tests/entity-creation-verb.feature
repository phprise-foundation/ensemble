# language: pt-BR
Funcionalidade: Criação e edição de team e workflow pela sidebar
  Origem: KAN-29. O verbo nasce do fato (criar vs. editar), nunca de um id derivado.
  Mesmo padrão de source (KAN-20): só deriva o slug quando o rascunho já existe.

  Cenário: Novo team envia POST /api/teams
    Dado que o usuário clica em "New team"
    Quando informa um nome e confirma
    Então a requisição usa o verbo POST em /api/teams
    E o id permanece vazio para o saveEntity decidir pela criação

  Cenário: Team existente envia PATCH /api/teams/:id
    Dado que o usuário edita um team existente
    Quando confirma o formulário
    Então a requisição usa PATCH /api/teams/:id
    E o id existente é preservado

  Cenário: Novo workflow envia POST /api/workflows
    Dado que o usuário clica em "New workflow"
    Quando informa um nome e confirma
    Então a requisição usa o verbo POST em /api/workflows
    E o id permanece vazio para o saveEntity decidir pela criação

  Cenário: Workflow existente envia PATCH /api/workflows/:id
    Dado que o usuário edita um workflow existente
    Quando confirma o formulário
    Então a requisição usa PATCH /api/workflows/:id
    E o id existente é preservado
