# language: pt-BR
Funcionalidade: Semântica do id no servidor — chave imutável e higiene do flag
  Segunda ponta da norma de docs/id-semantics-guide.md (KAN-42, lado servidor
  congelado no KAN-46). Os handlers garatem o id da URL como chave, nunca aplicam
  id divergente em silêncio e nunca persistem o flag de transporte __existing.

  Cenário: PATCH de coleção com id divergente no corpo é recusado
    Dado um registro de coleção cujo id é a chave
    Quando um PATCH é enviado a /api/<coleção>/:id com um corpo cujo id diverge da URL
    Então a resposta é 409 "<coleção> id divergente da URL: o id é imutável"
    E o registro permanece intacto, com o id da URL e o nome anterior

  Cenário: PATCH de source com id divergente no corpo é recusado
    Dado uma source registrada
    Quando um PATCH é enviado a /api/sources/:id com um corpo cujo id diverge da URL
    Então a resposta é 409 "sources id divergente da URL: o id é imutável"
    E o registro permanece intacto, com o id da URL e o nome anterior

  Cenário: PATCH de issue com id divergente no corpo fixa a chave
    Dado uma issue existente
    Quando um PATCH é enviado a /api/issues/:id com um corpo cujo id diverge da URL
    Então a resposta é 200 e o registro permanece com o id da URL
    E os demais campos do corpo são aplicados, padronizado com os demais handlers

  Cenário: POST de coleção não persiste o flag de transporte
    Quando um POST é enviado a /api/<coleção> com __existing no corpo
    Então o registro nasce sem o flag __existing, que nunca vaza pela API

  Cenário: POST de source não persiste o flag de transporte
    Quando um POST é enviado a /api/sources com __existing no corpo
    Então o registro nasce sem o flag __existing, que nunca vaza pela API
