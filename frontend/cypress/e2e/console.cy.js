describe('console grid', () => {
  beforeEach(() => {
    cy.viewport(1280, 800)
    cy.visitApp('/')
  })

  it('opens from the top button and exposes the live log', () => {
    cy.get('#app-console').should('not.exist')
    cy.get('.console-toggle').click()
    cy.get('#app-console').should('be.visible')
    cy.get('.console-output[role="log"]').should('have.attr', 'aria-live', 'polite')
    cy.get('form.console-input').should('have.attr', 'aria-label', 'Entrada do console')
    cy.get('.console-input-field').should('have.attr', 'placeholder', 'Digite um comando… (/help)')
    cy.get('.console-input-send').should('have.text', 'Enviar')
    cy.get('.console-filter').should('have.attr', 'aria-label', 'Filtrar por ator')
    cy.get('.console-filter option').first().should('have.text', 'Todas')
    cy.get('.console-toggle').should('have.attr', 'aria-expanded', 'true')
    cy.get('body').type('{ctrl}j')
    cy.get('#app-console').should('not.exist')
  })

  it('runs /help and /clear typed in the console input', () => {
    cy.get('.console-toggle').click()
    cy.get('.console-input input').type('/help{enter}')
    cy.get('.console-output').should('contain.text', '/status')
    cy.get('.console-input input').clear().type('/clear{enter}')
    cy.get('.console-output .console-line').should('not.exist')
    cy.get('.console-output').should('contain.text', 'Nenhuma mensagem')
  })

  it('runs /help and /clear in the light theme', () => {
    cy.get('.console-toggle').click()
    cy.get('button[title="Light theme"]').click()
    cy.get('.console-input input').type('/help{enter}')
    cy.get('.console-output').should('contain.text', '/status')
    cy.get('.console-input input').type('/clear{enter}')
    cy.get('.console-output .console-line').should('not.exist')
  })
})
