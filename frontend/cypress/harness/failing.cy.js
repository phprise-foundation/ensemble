describe('fixture vermelha do harness', () => {
  it('reprova de proposito para provar o codigo de saida', () => {
    cy.visit('/')
    cy.get('#app').should('not.be.empty')
    cy.then(() => {
      throw new Error('fixture vermelha de proposito: o harness precisa sair diferente de zero')
    })
  })
})
