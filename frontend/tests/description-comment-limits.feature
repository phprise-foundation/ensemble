# language: pt-BR
Funcionalidade: Limites de descrição e comentário

  Cenário: Limite exato de caracteres na descrição
    Dado que um texto com 4095 caracteres
    Quando tento salvar a descrição
    Então a operação é bem-sucedida

  Cenário: Limite exato de caracteres no comentário
    Dado que um texto com 1023 caracteres
    Quando tento salvar o comentário
    Então a operação é bem-sucedida

  Cenário: Exceder o limite de caracteres na descrição
    Dado que um texto com 4096 caracteres
    Quando tento salvar a descrição
    Então a operação falha com erro de limite excedido

  Cenário: Exceder o limite de caracteres no comentário
    Dado que um texto com 1024 caracteres
    Quando tento salvar o comentário
    Então a operação falha com erro de limite excedido

  Cenário: Sem gravação parcial de descrição
    Dado que um texto com 4096 caracteres
    Quando tento salvar a descrição
    Então não há gravação parcial

  Cenário: Sem gravação parcial de comentário
    Dado que um texto com 1024 caracteres
    Quando tento salvar o comentário
    Então não há gravação parcial

  Cenário: Contagem de code points para descrição
    Dado que um texto com 4095 code points
    Quando tento salvar a descrição
    Então a operação é bem-sucedida

  Cenário: Contagem de code points para comentário
    Dado que um texto com 1023 code points
    Quando tento salvar o comentário
    Então a operação é bem-sucedida

  Cenário: Limite de 90% para descrição
    Dado que um texto com 3685 caracteres (90% do limite)
    Quando tento salvar a descrição
    Então a operação é bem-sucedida e exibe aviso de limite

  Cenário: Limite de 90% para comentário
    Dado que um texto com 920 caracteres (90% do limite)
    Quando tento salvar o comentário
    Então a operação é bem-sucedida e exibe aviso de limite

  Cenário: Edição e cancelamento de descrição
    Dado que estou editando uma descrição
    Quando cancelo a edição
    Então a descrição original é mantida

  Cenário: Edição e cancelamento de comentário
    Dado que estou editando um comentário
    Quando cancelo a edição
    Então o comentário original é mantido

  Cenário: Validação legada de descrição
    Dado que uma descrição com caracteres legados
    Quando tento salvar
    Então a validação é aplicada corretamente

  Cenário: Validação legada de comentário
    Dado que um comentário com caracteres legados
    Quando tento salvar
    Então a validação é aplicada corretamente

  Cenário: ARIA para limites de descrição
    Dado que estou preenchendo uma descrição
    Quando alcanço o limite
    Então o elemento ARIA é atualizado

  Cenário: ARIA para limites de comentário
    Dado que estou preenchendo um comentário
    Quando alcanço o limite
    Então o elemento ARIA é atualizado

  Cenário: HTTP 422 ao exceder o limite
    Dado que tento salvar um texto excedente
    Quando o backend retorna 422
    Então o erro é tratado corretamente