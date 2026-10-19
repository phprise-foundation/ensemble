Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })
const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) => errors.push(`unhandledrejection: ${event.reason}`))
}

describe('KAN-91 rail colapsado', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => { errors.push(`console.error: ${args.join(' ')}`) })
    })
    cy.viewport(1280, 800)
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => { expect(errors, errors.join('\n')).to.have.length(0) })
  })

  it('mostra so folhas no rail e volta para a arvore', () => {
    cy.visit('/')
    cy.get('.nav .leaf').then(($leaves) => {
      const treeCount = $leaves.length
      cy.get('button.sidebar-toggle').click()
      cy.get('#app-sidebar').should('have.class', 'collapsed')
      cy.get('nav.rail[aria-label="Pages"]').should('exist')
      cy.get('.rail .leaf').should('have.length', treeCount + 1)
      cy.get('.rail').find('.nav-entry, .chevron, .entry-actions, .badge-id, .source-path, .label').should('not.exist')
      cy.get('.rail .rail-divider').should('have.length.greaterThan', 0)
      cy.get('#app-sidebar .brand > span:not(.brand-icon)').should('not.be.visible')
      cy.get('#app-sidebar').invoke('outerWidth').should('eq', 48)
      cy.get('.rail .leaf svg').each(($svg) => { cy.wrap($svg).should('have.attr', 'width', '18') })
      cy.get('.rail .rail-leaf').each(($leaf) => {
        cy.wrap($leaf).should('have.attr', 'title').and('not.be.empty')
        cy.wrap($leaf).should('have.attr', 'aria-label').and('not.be.empty')
        cy.wrap($leaf).should('have.css', 'width', '36px').and('have.css', 'height', '36px')
      })
      cy.get('.rail .leaf').last().click()
      cy.location('hash').should('eq', '#/experiences')
      cy.get('.rail .leaf.router-link-active').should('have.attr', 'aria-current', 'page')
      cy.get('button.sidebar-toggle').click()
      cy.get('#app-sidebar').should('not.have.class', 'collapsed')
      cy.get('nav.rail').should('not.exist')
      cy.get('.nav .leaf').should('have.length', treeCount)
    })
  })
})
