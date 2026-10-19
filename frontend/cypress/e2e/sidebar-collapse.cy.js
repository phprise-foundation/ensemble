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

  it('troca a árvore expandida pelo rail com ícones, tooltips e navegação, e volta ao expandido', () => {
    cy.visit('/#/boards/KANB')
    cy.get('.nav-entry[title="Boards"]').should('have.class', 'open')
    cy.get('nav:not(.rail) .leaf .label').should('have.length.greaterThan', 0)
    cy.get('nav:not(.rail) .leaf .label').first().should('be.visible')

    cy.get('button.sidebar-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'false')

    cy.get('nav.rail').should('have.attr', 'aria-label', 'Pages')
    cy.get('nav:not(.rail)').should('not.exist')
    cy.get('nav.rail .rail-leaf svg').should('have.length.greaterThan', 0)
    cy.get('nav.rail .rail-leaf svg').each(($icon) => {
      cy.wrap($icon).should('be.visible')
    })
    cy.get('nav.rail a.leaf.rail-leaf').each(($leaf) => {
      cy.wrap($leaf).should('have.attr', 'title').and('not.be.empty')
      cy.wrap($leaf).should('have.attr', 'aria-label').and('not.be.empty')
      cy.wrap($leaf).then(($node) => {
        expect($node.attr('aria-label')).to.eq($node.attr('title'))
      })
    })

    cy.get('nav.rail .rail-leaf').first().click()
    cy.location('hash').should('eq', '#/boards/KANB')
    cy.get('vite-error-overlay').should('not.exist')

    cy.get('button.sidebar-toggle').click()
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
    cy.get('nav.rail').should('not.exist')
    cy.get('nav:not(.rail) .leaf .label').first().should('be.visible')
  })
})
