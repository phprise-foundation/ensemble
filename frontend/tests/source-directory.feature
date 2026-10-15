# language: pt-BR
Funcionalidade: Validação de diretório ao registrar source
  Origem: KAN-11 (evidência do KAN-28; contrato congelado em docs/KAN-11-guide.md, seção 3).
  O registro é exercido no processo do anfitrião: o teste chama handle()
  (middleware simulado) como o cliente usaria a requisição HTTP, sobre
  uma db em memória e com sistema de arquivos real.

  Regra herdada do design aprovado: o verbo nasce do fato (criar vs. editar,
  KAN-20) e a validade do diretório é conferida pelo anfitrião — o navegador
  apenas reflete a resposta.

  Cenário: Criar source com diretório válido
    Dado um diretório que existe no anfitrião
    Quando o cliente envia POST /api/sources com caminho e nome indicados
    Então a resposta é 201 com a source criada
    E a source passa a listar em GET /api/sources com o nome indicado
    E a source pertence ao workspace indicado

  Cenário: Nome ausente deriva do último diretório do caminho
    Dado um diretório aninhado "projetos" no anfitrião
    Quando o cliente envia POST /api/sources com caminho e nome vazio
    Então a resposta é 201
    E o nome da source é "projetos", último diretório do caminho
    E o id da source é o slug derivado do nome

  Cenário: Caminho vazio recusa com a causa verdadeira
    Quando o cliente envia POST /api/sources com caminho vazio
    Então a resposta é 422 com "informe o caminho do diretório"
    E o mesmo vale quando o campo caminho não é enviado
    E a coleção de sources permanece vazia

  Cenário: Caminho inexistente recusa com a causa verdadeira
    Quando o cliente envia POST /api/sources com caminho inexistente no anfitrião
    Então a resposta é 422 com "diretório não encontrado"
    E a mensagem genérica "sources not found" não consta em lugar nenhum
    E a coleção de sources permanece vazia

  Cenário: Caminho que não é diretório recusa como diretório não encontrado
    Dado um arquivo regular no anfitrião
    Quando o cliente envia POST /api/sources com o caminho do arquivo
    Então a resposta é 422 com "diretório não encontrado"

  Cenário: Usos repetidos com diretórios distintos fluem sem atrito
    Dado dois diretórios distintos no anfitrião
    Quando o cliente registra os dois em POST /api/sources
    Então os dois recebem 201
    E GET /api/sources lista os dois, cada um com seu id próprio

  Cenário: Duplicata deliberada recebe recusa clara
    Dado uma pasta já registrada como source
    Quando o cliente envia POST /api/sources para a mesma pasta
    Então a resposta é 409 com "source id já existe"
    E a source original permanece listada sem duplicação

  Cenário: Pastas distintas com o mesmo nome colidem no identificador
    Dado duas pastas distintas com o mesmo último nome no anfitrião
    Quando a segunda pasta é registrada em POST /api/sources
    Então a resposta é 409 com "source id já existe"

  Cenário: Edição renomeia e mantém o id estável
    Dado uma source existente registrada a partir de uma pasta
    Quando o cliente envia PATCH /api/sources/:id com um novo nome
    Então a resposta é 200 com a source atualizada
    E o id permanece o mesmo da criação
    E GET /api/sources lista uma única source para aquela pasta

  Cenário: Edição de source inexistente recusa com 404
    Quando o cliente envia PATCH /api/sources/:id para um id inexistente
    Então a resposta é 404 com "source não encontrada"
    E nunca mais "sources not found"
    E mesmo com caminho inexistente no corpo a resposta continua 404,
      pois antes de validar dados o id precisa existir

  Cenário: Edição com caminho inexistente recusa com 422
    Dado uma source existente
    Quando o cliente envia PATCH /api/sources/:id com caminho inexistente no anfitrião
    Então a resposta é 422 com "diretório não encontrado"
    E a source mantém o caminho e o nome originais

  Cenário: Remoção segue funcionando
    Dado uma source existente
    Quando o cliente envia DELETE /api/sources/:id
    Então a resposta é 200 confirmando o id removido
    E a source some de GET /api/sources
