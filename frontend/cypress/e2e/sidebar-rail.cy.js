Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('rail do sidebar colapsado', () => {
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

  it('exibe um link para cada folha, sem elementos mortos, com tooltip, navegação e destaque ativo', () => {
    cy.visit('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('.leaf').its('length').then((treeLeaves) => {
      cy.get('button.sidebar-toggle').click()
      cy.get('#app-sidebar').should('have.class', 'collapsed')
      cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'false')

      cy.get('nav.rail').should('have.attr', 'aria-label', 'Pages')
      cy.get('nav.rail a.leaf.rail-leaf').should('have.length', treeLeaves + 1)
      cy.get('nav.rail .rail-divider').should('have.length.greaterThan', 0)

      cy.get('nav.rail .rail-leaf svg').first().then(($svg) => {
        expect(Math.round($svg[0].getBoundingClientRect().width)).to.eq(18)
        expect(Math.round($svg[0].getBoundingClientRect().height)).to.eq(18)
      })

      cy.get('#app-sidebar').then(($sidebar) => {
        expect(Math.round($sidebar[0].getBoundingClientRect().width)).to.eq(48)
      })
      cy.get('nav.rail').then(($rail) => {
        expect($rail[0].scrollWidth).to.be.at.most($rail[0].clientWidth)
      })

      cy.get('nav.rail .nav-entry, nav.rail .chevron, nav.rail .icon-btn, nav.rail .badge-id, nav.rail .source-path, nav.rail .label > span').should('not.exist')
      cy.get('.nav').should('not.be.visible')
      cy.get('#app-sidebar .brand').then(($brand) => {
        expect($brand[0].getBoundingClientRect().width).to.eq(0)
      })

      cy.get('nav.rail a.rail-leaf').each(($link) => {
        expect($link.attr('title'), 'title').to.be.a('string').and.not.be.empty
        expect($link.attr('aria-label'), 'aria-label').to.eq($link.attr('title'))
      })

      cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.length', 1)
      cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.attr', 'title', 'Kanban')

      cy.get('nav.rail a.rail-leaf').last().click({ force: true })
      cy.hash().should('eq', '#/experiences')
      cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.length', 1)
      cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.class', 'router-link-active')

      cy.get('button.sidebar-toggle').click()
      cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'true')
      cy.get('nav.rail').should('not.exist')
      cy.get('.nav').should('be.visible')
      cy.get('.leaf').should('have.length', treeLeaves)
    })
  })

  it('mantém a árvore expandida oculta em 1024px e exibe o rail a partir de 1025px', () => {
    cy.viewport(1024, 800)
    cy.visit('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button.sidebar-toggle').should('not.be.visible')
    cy.get('.nav').should('be.visible')
    cy.get('nav.rail').should('not.exist')

    cy.viewport(1025, 800)
    cy.get('button.sidebar-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('nav.rail a.rail-leaf').should('have.length.greaterThan', 0)
  })
})
