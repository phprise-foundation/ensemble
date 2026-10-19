// Artefato de QA (Mai) — KAN-30: prova o toggle, o scrim, o Escape e a rota do drawer
// na interface em execução, não apenas no texto-fonte.
// Os screenshots de falha travam neste Electron headless (problema de captura conhecido);
// desligados para que a falha real da asserção seja reportada em vez da captura.
Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })
const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('drawer mobile na interface em execução', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.viewport(760, 900)
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('abre pelo handler do toggle do menu e fecha pelo scrim', () => {
    cy.viewport(1000, 900)
    cy.visit('/')
    cy.get('#app-sidebar')
      .should('not.have.class', 'open')
      .and('have.attr', 'aria-hidden', 'true')
    cy.get('.content').should('not.have.attr', 'inert')
    cy.get('button.menu-toggle')
      .should('have.attr', 'aria-expanded', 'false')
      .and('have.attr', 'aria-controls', 'app-sidebar')
    cy.get('button.menu-toggle').click()
    cy.get('#app-sidebar')
      .should('have.class', 'open')
      .and('have.attr', 'role', 'dialog')
      .and('have.attr', 'aria-modal', 'true')
      .and('have.attr', 'aria-hidden', 'false')
    cy.get('button.menu-toggle').should('have.attr', 'aria-expanded', 'true')
    cy.get('.content').should('have.attr', 'inert')
    cy.get('.scrim').should('exist')
    cy.get('.scrim').click()
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('.scrim').should('not.exist')
    cy.get('.content').should('not.have.attr', 'inert')
    cy.document().then((doc) => {
      expect(doc.activeElement.className, 'foco volta para o toggle').to.include('menu-toggle')
    })
  })

  it('fecha quando a tecla Escape é pressionada com o overlay aberto', () => {
    cy.visit('/')
    cy.get('button.menu-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'open')
    cy.get('.scrim').should('exist')
    cy.get('body').type('{esc}')
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('.scrim').should('not.exist')
  })

  it('fecha quando uma navegação de rota acontece com o overlay aberto', () => {
    cy.visit('/')
    cy.get('button.menu-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'open')
    cy.get('.nav a.leaf').first().click()
    cy.url().should('match', /#\/boards\//)
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('.scrim').should('not.exist')
  })
})
