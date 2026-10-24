Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const EXPECTED_SLOTS = ['grid-left', 'grid-bottom', 'grid-right', 'theme', 'profile']

function breakpoint() {
  return cy.document().then((doc) => {
    const declared = getComputedStyle(doc.documentElement).getPropertyValue('--breakpoint-sidebar')
    const value = Number.parseFloat(declared)
    expect(value, 'corte do sidebar declarado em CSS').to.be.greaterThan(0)
    return value
  })
}

function viewportFromBreakpoint(offset) {
  cy.visit('/?sim=1')
  breakpoint().then((value) => {
    cy.viewport(Math.round(value) + offset, 900)
    cy.document().should((doc) => {
      expect(doc.documentElement.clientWidth, 'largura da janela').to.eq(Math.round(value) + offset)
    })
    cy.reload()
  })
}

function slots() {
  return cy.get('.topbar-actions [data-topbar-slot]')
}

function slotNames() {
  return cy.get('.topbar-actions [data-topbar-slot]').then(($slots) =>
    [...$slots].map((slot) => slot.getAttribute('data-topbar-slot')),
  )
}

describe('ordem dos controles do topbar (KAN-87)', () => {
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

  it('em desktop mantém a ordem do DOM e o perfil como último item', () => {
    viewportFromBreakpoint(200)
    slots().should('have.length', EXPECTED_SLOTS.length)
    slotNames().should('deep.equal', EXPECTED_SLOTS)
    slots().then(($slots) => {
      expect([...$slots].at(-1).getAttribute('data-topbar-slot'), 'último item').to.eq('profile')
    })
  })

  it('em desktop a ordem visual acompanha a ordem do DOM', () => {
    viewportFromBreakpoint(200)
    slots().should('have.length', EXPECTED_SLOTS.length)
    cy.get('.topbar-actions').then(($actions) => {
      const lefts = [...$actions[0].children].map((child) => child.getBoundingClientRect().left)
      const ordered = [...lefts].sort((a, b) => a - b)
      expect(lefts).to.deep.equal(ordered)
      const group = $actions[0].getBoundingClientRect()
      expect([...lefts].at(-1)).to.be.lessThan(group.right)
    })
  })


  it('tema alterna e o perfil abre as opções de perfil e sair', () => {
    viewportFromBreakpoint(200)
    cy.get('[data-topbar-slot="theme"]').then(($theme) => {
      const before = $theme.attr('title')
      cy.get('[data-topbar-slot="theme"]').click()
      cy.get('[data-topbar-slot="theme"]').should('have.attr', 'title').and('not.eq', before)
    })
    cy.get('[data-topbar-slot="profile"] .profile-trigger, [data-topbar-slot="profile"] button').first().click()
    cy.get('[role="menuitem"]').should('have.length.greaterThan', 0)
  })

  it('em 1024px ou menos não há grade, o menu mobile permanece e não há overflow', () => {
    viewportFromBreakpoint(0)
    slotNames().should('deep.equal', ['theme', 'profile'])
    cy.get('.menu-toggle').should('be.visible')
    cy.get('[data-topbar-slot="theme"]').should('be.visible')
    cy.get('[data-topbar-slot="profile"]').should('be.visible')
    cy.window().then((win) => {
      expect(win.document.documentElement.scrollWidth).to.be.at.most(win.document.documentElement.clientWidth)
    })
  })

  it('em 1025px não há overflow no topo nem na página', () => {
    viewportFromBreakpoint(1)
    slots().should('have.length', EXPECTED_SLOTS.length)
    cy.window().then((win) => {
      expect(win.document.documentElement.scrollWidth).to.be.at.most(win.document.documentElement.clientWidth)
      cy.get('.topbar').then(($bar) => {
        expect($bar[0].getBoundingClientRect().right).to.be.at.most(win.innerWidth)
      })
    })
  })
})
