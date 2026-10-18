describe('fixture verde do harness', () => {
  it('renderiza o app', () => {
    cy.visit('/')
    cy.get('#app').should('not.be.empty')
    cy.get('#app-sidebar').should('exist')
  })
})
