Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const DESKTOP_SLOTS = ['sim', 'grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile']
const MOBILE_SLOTS = ['sim', 'theme', 'profile']

function open(label = 'topbar-order') {
  cy.visit('/?sim=1')
  cy.get('.topbar').should('be.visible')
  cy.get('vite-error-overlay').should('not.exist')
  cy.log(label)
}

function viewportFromBreakpoint(offset) {
  open('breakpoint')
  cy.document().then((doc) => {
    const declared = getComputedStyle(doc.documentElement).getPropertyValue('--breakpoint-sidebar')
    const breakpoint = Number.parseFloat(declared)
    expect(breakpoint, 'corte do sidebar declarado em CSS').to.be.greaterThan(0)
    cy.viewport(Math.round(breakpoint) + offset, 900)
  })
  cy.reload()
  cy.get('.topbar').should('be.visible')
}

function expectSlots(expected) {
  cy.get('.topbar-actions [data-topbar-slot]').then(($slots) => {
    const names = [...$slots].map((element) => element.getAttribute('data-topbar-slot'))
    expect(names, 'ordem DOM dos slots').to.deep.equal(expected)
  })
}

function expectNoOverflow() {
  cy.window().then((win) => {
    const root = win.document.documentElement
    expect(root.scrollWidth, 'overflow horizontal do documento').to.be.at.most(root.clientWidth)
    const topbar = win.document.querySelector('.topbar')
    expect(topbar.scrollWidth, 'overflow horizontal do topbar').to.be.at.most(topbar.clientWidth)
  })
}

describe('ordem dos controles do topbar na interface em execução', () => {
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
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('em 1280px a ordem DOM e visual é Sim, grade esquerda, grade inferior, grade direita, tema e perfil', () => {
    cy.viewport(1280, 900)
    open()

    expectSlots(DESKTOP_SLOTS)

    cy.get('.topbar-actions [data-topbar-slot]').then(($slots) => {
      const lefts = [...$slots].map((element) => element.getBoundingClientRect().left)
      expect(lefts, 'ordem visual da esquerda para a direita').to.deep.equal([...lefts].sort((a, b) => a - b))
      expect(new Set(lefts).size, 'cada controle em uma posição distinta').to.equal(lefts.length)

      const container = $slots[0].closest('.topbar-actions').getBoundingClientRect()
      const profile = [...$slots].at(-1).getBoundingClientRect()
      expect(profile.right, 'perfil encostado na borda direita do grupo').to.be.closeTo(container.right, 1)
    })

    expectNoOverflow()
  })

  it('Sim alterna a simulação, mantém o rótulo e publica aria-pressed', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('[data-topbar-slot="sim"]')
      .should('be.visible')
      .and('have.attr', 'aria-pressed', 'true')
      .and('contain.text', 'Sim')
    cy.get('[data-topbar-slot="sim"] .sim-dot').should('be.visible')

    cy.get('[data-topbar-slot="sim"]').click()
    cy.get('[data-topbar-slot="sim"]').should('have.attr', 'aria-pressed', 'false').and('contain.text', 'Sim')

    cy.get('[data-topbar-slot="sim"]').click()
    cy.get('[data-topbar-slot="sim"]').should('have.attr', 'aria-pressed', 'true')
  })

  it('grade esquerda alterna o sidebar por clique e Ctrl+B', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('[data-topbar-slot="grid-left"]')
      .should('have.attr', 'aria-controls', 'app-sidebar')
      .and('have.attr', 'aria-expanded', 'true')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('[data-topbar-slot="grid-left"]').click()
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('[data-topbar-slot="grid-left"]').should('have.attr', 'aria-expanded', 'false')

    cy.get('body').type('{ctrl+b}')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
    cy.get('[data-topbar-slot="grid-left"]').should('have.attr', 'aria-expanded', 'true')
  })

  it('grade direita alterna o copilot por clique e Ctrl+Alt+B', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('[data-topbar-slot="grid-right"]')
      .should('have.attr', 'aria-controls', 'app-copilot')
      .and('have.attr', 'aria-expanded', 'true')
    cy.get('#app-copilot').should('exist')

    cy.get('[data-topbar-slot="grid-right"]').click()
    cy.get('#app-copilot').should('not.exist')
    cy.get('[data-topbar-slot="grid-right"]').should('have.attr', 'aria-expanded', 'false')

    cy.get('body').type('{ctrl+alt+b}')
    cy.get('#app-copilot').should('exist')
    cy.get('[data-topbar-slot="grid-right"]').should('have.attr', 'aria-expanded', 'true')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
  })

  it('grade inferior alterna o console e expõe aria-expanded', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('[data-topbar-slot="grid-bottom"]')
      .should('have.attr', 'aria-controls', 'app-console')
      .and('have.attr', 'aria-expanded', 'false')
    cy.get('#app-console').should('not.exist')

    cy.get('[data-topbar-slot="grid-bottom"]').click()
    cy.get('#app-console').should('exist')
    cy.get('[data-topbar-slot="grid-bottom"]').should('have.attr', 'aria-expanded', 'true')

    cy.get('[data-topbar-slot="grid-bottom"]').click()
    cy.get('#app-console').should('not.exist')
  })

  it('tema alterna o modo e atualiza nome acessível e tooltip', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('html').should('have.attr', 'data-theme', 'dark')
    cy.get('[data-topbar-slot="theme"]')
      .should('have.attr', 'title', 'Light theme')
      .and('have.attr', 'aria-label', 'Light theme')

    cy.get('[data-topbar-slot="theme"]').click()
    cy.get('html').should('have.attr', 'data-theme', 'light')
    cy.get('[data-topbar-slot="theme"]')
      .should('have.attr', 'title', 'Dark theme')
      .and('have.attr', 'aria-label', 'Dark theme')

    cy.get('[data-topbar-slot="theme"]').click()
    cy.get('html').should('have.attr', 'data-theme', 'dark')
  })

  it('perfil é o último item e abre profile/logout', () => {
    cy.viewport(1280, 900)
    open()

    cy.get('[data-topbar-slot="profile"]').should('be.visible')
    cy.get('[data-topbar-slot="profile"]').find('.profile-trigger').click()
    cy.contains('.profile-dropdown [role="menuitem"]', 'profile').should('be.visible')
    cy.contains('.profile-dropdown [role="menuitem"]', 'logout').should('be.visible')
  })

  it('em 1024px nenhum controle de grade existe e os controles restantes seguem visíveis', () => {
    viewportFromBreakpoint(0)

    cy.get('[data-topbar-slot^="grid-"]').should('not.exist')
    expectSlots(MOBILE_SLOTS)
    cy.get('.sidebar-toggle').should('not.exist')
    cy.get('.console-toggle').should('not.exist')
    cy.get('.copilot-toggle').should('not.exist')
    cy.get('#app-copilot').should('not.exist')
    cy.get('.menu-toggle').should('be.visible')
    cy.get('[data-topbar-slot="sim"]').should('be.visible')
    cy.get('[data-topbar-slot="theme"]').should('be.visible')
    cy.get('[data-topbar-slot="profile"]').should('be.visible')

    expectNoOverflow()
  })

  it('em 1025px o grupo direito está completo, o título trunca e não há overflow', () => {
    viewportFromBreakpoint(1)

    cy.get('[data-topbar-slot^="grid-"]').should('have.length', 3)
    expectSlots(DESKTOP_SLOTS)
    cy.get('.topbar h1').should('have.css', 'text-overflow', 'ellipsis')
    cy.get('.menu-toggle').should('not.be.visible')

    expectNoOverflow()
  })
})
