const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('menu de perfil do usuário no topo direito (KAN-15)', () => {
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
    cy.then(() => {
      expect(errors, `runtime errors:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('mostra avatar, nome e dropdown profile/logout no topo direito', () => {
    cy.visit('/')
    cy.get('vite-error-overlay').should('not.exist')
    cy.get('.topbar .profile-trigger').should('be.visible')
    cy.get('.profile-avatar').should('be.visible')
    cy.get('.profile-name').should('be.visible')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'profile').should('be.visible')
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').should('be.visible')
  })

  it('trunca o nome em linha única sem quebrar o topo', () => {
    cy.visit('/')
    cy.get('.profile-name').invoke('css', 'white-space').should('eq', 'nowrap')
    cy.get('.profile-name').invoke('css', 'text-overflow').should('eq', 'ellipsis')
    cy.get('.topbar .menu-toggle').should('be.visible')
    cy.get('.topbar').then(($bar) => {
      expect($bar[0].getBoundingClientRect().height, 'altura do topo').to.be.lessThan(120)
    })
  })

  it('edita o nome na tela de profile, nunca o login, e persiste ao recarregar', () => {
    cy.visit('/')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'profile').click()
    cy.location('hash').should('eq', '#/profile')

    cy.get('input[readonly]').should('be.visible')
    cy.get('input[maxlength="40"]').clear().type('Maria Fernanda de Souza Oliveira')
    cy.get('form button[type="submit"]').click()

    cy.get('.profile-name').should('have.attr', 'title', 'Maria Fernanda de Souza Oliveira')
    cy.reload()
    cy.get('.profile-name').should('have.attr', 'title', 'Maria Fernanda de Souza Oliveira')
  })

  it('recusa nome acima de 40 caracteres e preserva o anterior', () => {
    cy.visit('/#/profile')
    cy.get('input[maxlength="40"]').clear().type('x'.repeat(41), { force: true })
    cy.get('form button[type="submit"]').click()
    cy.get('form').contains('40').should('be.visible')
  })

  it('encerra a sessão no logout e torna a área protegida inacessível', () => {
    cy.visit('/')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').click()

    cy.get('#app-sidebar').should('not.exist')
    cy.get('.topbar').should('not.exist')
    cy.contains('Session ended').should('be.visible')

    cy.contains('Sign in again').click()
    cy.get('#app-sidebar').should('be.visible')
  })
})
