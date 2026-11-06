const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('branding', () => {
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

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `runtime errors:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('uses the bluesmoke mark in the shell and as the favicon', () => {
    cy.visitApp('/')

    cy.get('.brand-icon .ensemble-logo')
      .should('have.attr', 'src')
      .and('include', 'ens-logo-bluesmoke')
    cy.get('link[rel="icon"]').should('have.attr', 'href').and('include', 'favicon.svg')
  })

  it('uses the white mark on the dark sign-in gate', () => {
    cy.visitApp('/')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').click()

    cy.get('.session-ended-logo')
      .should('have.attr', 'src')
      .and('include', 'ens-logo-white')
  })

  it('uses the black mark on the light sign-in gate', () => {
    cy.request('POST', '/api/auth/login', { login: 'admin', password: 'admin1234' })
      .its('body.token')
      .then((token) => {
        cy.visit('/app/', {
          onBeforeLoad(win) {
            win.localStorage.setItem('ensemble.token', token)
          },
        })
      })
    cy.get('[data-topbar-slot="theme"]').click()
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').click()

    cy.get('.session-ended-logo')
      .should('have.attr', 'src')
      .and('include', 'ens-logo-black')
  })
})
