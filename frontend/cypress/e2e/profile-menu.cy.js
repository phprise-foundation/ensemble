Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const FULL_NAME = 'Maria Fernanda de Souza Oliveira'

function viewportFromBreakpoint(offset) {
  cy.visit('/')
  cy.document().then((doc) => {
    const declared = getComputedStyle(doc.documentElement).getPropertyValue('--breakpoint-sidebar')
    const breakpoint = Number.parseFloat(declared)
    expect(breakpoint, 'corte do sidebar declarado em CSS').to.be.greaterThan(0)
    cy.viewport(Math.round(breakpoint) + offset, 800)
  })
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
    cy.request('PATCH', '/api/user', { displayName: '' })
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('mostra avatar, nome e dropdown profile/logout no topo direito', () => {
    viewportFromBreakpoint(200)
    cy.visit('/')
    cy.get('.topbar .profile-trigger').should('be.visible')
    cy.get('.profile-avatar')
      .should('be.visible')
      .then(($avatar) => {
        const avatar = $avatar[0].getBoundingClientRect()
        expect(Math.round(avatar.width), 'largura do avatar').to.be.closeTo(22, 1)
        expect(Math.round(avatar.height), 'altura do avatar').to.be.closeTo(22, 1)
      })
    cy.get('.profile-name').should('be.visible').and('have.text', 'admin')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'profile').should('be.visible')
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').should('be.visible')
  })

  it('trunca o nome em linha única sem quebrar o topo', () => {
    cy.request('PATCH', '/api/user', { displayName: FULL_NAME })
    viewportFromBreakpoint(200)
    cy.visit('/')
    cy.get('.profile-name').should('have.text', FULL_NAME).and('have.attr', 'title', FULL_NAME)
    cy.get('.profile-name').then(($name) => {
      const name = $name[0]
      const style = getComputedStyle(name)
      expect(style.whiteSpace, 'linha única').to.eq('nowrap')
      expect(style.textOverflow, 'reticências').to.eq('ellipsis')
      expect(name.scrollWidth, 'nome completo excede a caixa').to.be.greaterThan(name.clientWidth)
      expect(name.getBoundingClientRect().height, 'altura de uma linha').to.be.lessThan(30)
    })
    cy.get('.topbar').then(($bar) => {
      expect($bar[0].getBoundingClientRect().height, 'altura do topo').to.be.lessThan(120)
    })
  })

  it('em tela pequena o perfil interage, o nome fica em linha única e o topo não estoura', () => {
    viewportFromBreakpoint(-8)
    cy.visit('/#/profile')
    cy.get('#profile-name').clear().type(FULL_NAME)
    cy.get('form button[type="submit"]').click()
    cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')

    cy.get('.topbar .profile-name').should('have.text', FULL_NAME)
    cy.get('.topbar .profile-name').then(($name) => {
      const name = $name[0]
      expect(getComputedStyle(name).whiteSpace, 'linha única').to.eq('nowrap')
      expect(name.scrollWidth, 'nome completo excede a caixa').to.be.greaterThan(name.clientWidth)
    })
    cy.get('.topbar .menu-toggle').should('be.visible')
    cy.get('.topbar').then(($bar) => {
      expect($bar[0].getBoundingClientRect().height, 'altura do topo').to.be.lessThan(120)
    })
    cy.window().then((win) => {
      cy.get('.topbar').then(($bar) => {
        expect($bar[0].getBoundingClientRect().right, 'borda direita do topo').to.be.at.most(win.innerWidth)
      })
    })
  })

  it('edita o nome na tela de profile, nunca o login, e persiste ao recarregar', () => {
    cy.visit('/')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'profile').click()
    cy.location('hash').should('eq', '#/profile')

    cy.get('#profile-login').then(($login) => {
      const login = $login.val()
      expect($login[0].readOnly || $login[0].disabled, 'login não editável').to.eq(true)
      cy.get('#profile-name').clear().type(FULL_NAME)
      cy.get('form button[type="submit"]').click()
      cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
      cy.get('#profile-login').should('have.value', login)
    })

    cy.get('.profile-name').should('have.attr', 'title', FULL_NAME)
    cy.get('.profile-trigger').should('have.attr', 'aria-label').and('contain', FULL_NAME)

    cy.reload()
    cy.get('#profile-name').should('have.value', FULL_NAME)
    cy.get('#profile-login')
      .should('have.value', 'admin')
      .and(($login) => expect($login[0].readOnly || $login[0].disabled, 'login não editável').to.eq(true))
    cy.get('.profile-name').should('have.attr', 'title', FULL_NAME)
  })

  it('limita o nome a 40 caracteres na entrada e salva o valor aceito', () => {
    cy.visit('/#/profile')
    cy.get('#profile-name').clear().type('x'.repeat(41))
    cy.get('#profile-name').invoke('val').should('have.length', 40)
    cy.get('form button[type="submit"]').click()
    cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
    cy.get('.profile-name').should('have.text', 'x'.repeat(40))
  })

  it('encerra a sessão no logout e torna a área protegida inacessível', () => {
    cy.visit('/')
    cy.get('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').click()

    cy.get('#app-sidebar').should('not.exist')
    cy.get('.topbar').should('not.exist')
    cy.contains('Session ended').should('be.visible')

    cy.contains('Sign in again').click()
    cy.get('.topbar .profile-trigger').should('be.visible')
    cy.get('#app-sidebar').should('exist')
  })
})
