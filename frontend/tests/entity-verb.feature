# language: pt-BR
Funcionalidade: Semântica do id nos formulários: verbo nasce do fato (__existing), não do campo id
  Origem: KAN-42, follow-up do KAN-29 e do padrão do KAN-20 (ver docs/id-semantics-guide.md).
  Norma congelada: o verbo (POST vs PATCH) deriva do fato de existência — o flag
  __existing — nunca do campo data.id. O id é a chave imutável da entidade: aceito
  na criação (board exige; workflow/team aceitam), nunca editável na edição.

  Cenário: Criar board com id digitado usa POST
    Dado que o usuário abre "New board" e o formulário nasce sem o flag __existing
    Quando digita um id válido de 3 a 6 letras, um nome e confirma
    Então a requisição usa o verbo POST para /api/boards
    E o id digitado é respeitado em vez de virar PATCH de entidade inexistente
    E o flag __existing não é enviado no payload

  Cenário: Novo workflow com id digitado usa POST
    Dado que o usuário abre "New workflow" e o formulário nasce sem o flag __existing
    Quando digita um id, um nome e confirma
    Então a requisição usa o verbo POST para /api/workflows
    E o id digitado é respeitado

  Cenário: Novo team com id digitado usa POST
    Dado que o usuário abre "New team" e preenche o campo "ID (optional)"
    Quando informa um nome e confirma
    Então a requisição usa o verbo POST para /api/teams
    E o id digitado é respeitado em vez de virar PATCH de entidade inexistente

  Cenário: Copiar template usa POST para as entidades filhas
    Dado que existe um workflow template com statuses, transitions e issue types
    Quando o usuário cria um novo workflow a partir do template
    Então o workflow é criado via POST /api/workflows
    E cada status copiado é criado via POST /api/statuses com o id pré-gerado
    E cada transition copiada é criada via POST /api/transitions
    E cada issue type copiado é criado via POST /api/issueTypes com o id pré-gerado
    E nenhuma entidade nova recebe PATCH

  Cenário: Editar workflow não expõe o id e envia PATCH
    Dado que o usuário abre "Edit workflow" de uma entidade existente
    Então o modal não expõe o campo "ID" para edição
    Quando informa um novo nome e confirma
    Então a requisição usa PATCH /api/workflows/:id
    E o id existente é preservado
    E o flag __existing não é enviado no payload

  Cenário: Editar board, team ou source existentes usa PATCH
    Dado que o usuário edita uma entidade existente pela sidebar
    Quando confirma o formulário
    Então a requisição usa PATCH em /api/<colecao>/:id
    E o id é preservado
    E o flag __existing não é enviado no payload

  Cenário: Editar via páginas CRUD usa PATCH
    Dado que o usuário edita um agente ou issue type existente
    Quando confirma o formulário
    Então a requisição usa PATCH em /api/agents/:id ou /api/issueTypes/:id
    E o id é preservado
    E o flag __existing não é enviado no payload

  Cenário: Criar com id vazio segue sendo POST
    Dado que o usuário cria uma entidade deixando o id vazio
    Quando confirma o formulário
    Então a requisição usa o verbo POST
    E o backend gera o id

  Cenário: Cenário E2E no navegador (Cypress)
    Dado que a aplicação está a correr no navegador
    Quando o usuário cria um workflow digitando um id e salva
    Então o backend recebe POST /api/workflows sem o flag __existing
    Quando o usuário edita um workflow existente e salva
    Então o modal não mostra campo de id editável e o backend recebe PATCH
