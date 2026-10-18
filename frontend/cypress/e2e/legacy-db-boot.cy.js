const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('KAN-56 — boot sobre um db.json antigo, sem a chave user', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => errors.push(`console.error: ${args.join(' ')}`))
    })
  })

  afterEach(() => {
    cy.then(() => {
      expect(errors, `runtime errors:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('sobe a interface e o menu de perfil mostra o login vindo do backfill', () => {
    cy.request({ url: '/api/user', failOnStatusCode: false }).then((response) => {
      expect(response.status, JSON.stringify(response.body)).to.eq(200)
      expect(response.body.login).to.eq('admin')

      cy.visit('/')
      cy.get('vite-error-overlay').should('not.exist')
      cy.get('.topbar .profile-trigger').should('be.visible')
      cy.get('.profile-name').should('have.text', response.body.displayName || response.body.login)
      cy.get('#app-sidebar').should('exist')
      cy.get('.column').should('have.length.at.least', 1)
    })
  })

  it('PATCH /api/user persiste o nome e mantém o login', () => {
    cy.request('PATCH', '/api/user', { displayName: 'Legacy QA' }).then((response) => {
      expect(response.status).to.eq(200)
      expect(response.body).to.deep.eq({ login: 'admin', displayName: 'Legacy QA' })
    })

    cy.visit('/')
    cy.get('.profile-name').should('have.text', 'Legacy QA')
    cy.reload()
    cy.get('.profile-name').should('have.text', 'Legacy QA')

    cy.request('PATCH', '/api/user', { displayName: '' })
  })
})
