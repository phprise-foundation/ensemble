# language: pt-BR
Funcionalidade: Criação de source pela sidebar
  Origem: KAN-20.

  Cenário: Nova source envia POST /api/sources
    Dado que o usuário clica em "New source"
    Quando preenche um diretório válido e confirma
    Então a requisição usa o verbo POST em /api/sources
    E não envia PATCH em /api/sources/:id para item inexistente

  Cenário: Edição de source existente envia PATCH /api/sources/:id
    Dado que o usuário edita uma source existente
    Quando confirma o formulário
    Então a requisição usa PATCH /api/sources/:id
