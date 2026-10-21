// Fixture do harness (fora de cypress/e2e/, invisível à rodada padrão):
// grava através do backend fake para provar onde a persistência aterrissa.
describe('fixture do harness que grava pelo backend fake', () => {
  it('atualiza o displayName pela API', () => {
    cy.request('PATCH', '/api/user', { displayName: 'Registro do contrato KAN-125' })
      .its('status')
      .should('equal', 200)
  })
})
