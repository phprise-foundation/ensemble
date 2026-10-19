const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('frontend smoke', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
  })

  it('boots and renders the board without runtime errors', () => {
    cy.visit('/')
    cy.get('vite-error-overlay').should('not.exist')
    cy.get('#app').should('not.be.empty')
    cy.get('.topbar').should('be.visible')
    cy.get('#app-sidebar').should('exist')
    cy.contains('Prototype backend').should('exist')
    cy.get('.column').should('have.length.at.least', 1)
    cy.get('.card .card-id')
      .first()
      .invoke('text')
      .then((text) => expect(text.trim()).to.match(/^[A-Z]+-\d+$/))
    cy.then(() => {
      expect(errors, `runtime errors:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('opens the console grid with Ctrl+J and keeps it closed by default', () => {
    cy.viewport(1280, 800)
    cy.visit('/')
    cy.get('#app-console').should('not.exist')
    cy.get('.console-toggle').should('have.attr', 'aria-expanded', 'false')
    cy.get('body').type('{ctrl}j')
    cy.get('#app-console').should('be.visible')
    cy.get('#app-console').should('have.css', 'height').and('not.eq', '0px')
    cy.get('.console-toggle').should('have.attr', 'aria-expanded', 'true')
    cy.get('body').type('{ctrl}j')
    cy.get('#app-console').should('not.exist')
    expect(errors, 'runtime errors').to.deep.equal([])
  })

  it('toggles the theme on the top bar control', () => {
    cy.visit('/')
    cy.get('html').should('have.attr', 'data-theme', 'dark')
    cy.get('button[title="Light theme"]').click()
    cy.get('html').should('have.attr', 'data-theme', 'light')
    cy.get('button[title="Dark theme"]').click()
    cy.get('html').should('have.attr', 'data-theme', 'dark')
  })
})
