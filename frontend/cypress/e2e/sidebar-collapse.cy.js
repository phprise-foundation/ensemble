Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('sidebar colapsado na interface em execução', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.viewport(1280, 800)
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('oculta os rótulos das folhas com submenu aberto e preserva ícones, títulos e navegação', () => {
    cy.visit('/')
    cy.get('.nav-entry[title="Boards"]').should('have.class', 'open')
    cy.get('.leaf .label').should('have.length.greaterThan', 0)
    cy.get('.leaf .label').first().should('be.visible')

    cy.get('button.sidebar-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'false')

    cy.get('nav:not(.rail)').should('not.exist')
    cy.get('.leaf .label').should('not.exist')
    cy.get('nav.rail .rail-leaf svg').should('have.length.greaterThan', 0)
    cy.get('nav.rail .rail-leaf svg').each(($icon) => {
      cy.wrap($icon).should('be.visible')
    })
    cy.get('nav.rail .rail-leaf').each(($leaf) => {
      cy.wrap($leaf).should('have.attr', 'title').and('not.be.empty')
    })

    cy.get('nav.rail .rail-leaf').first().click()
    cy.location('hash').should('eq', '#/boards/KANB')
    cy.get('vite-error-overlay').should('not.exist')
  })
})
