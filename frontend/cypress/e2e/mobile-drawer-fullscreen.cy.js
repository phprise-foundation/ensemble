// Artefato de QA (Mai) — KAN-96: prova o drawer em tela cheia abaixo de 768px em
// navegador real, cobrindo a matriz 320/375/767/768/1023/1024/1025, o lock do body,
// os alvos de 44px, os fechos e a ausência de overflow e de erros de console.
// Sem screenshots por serem desligados no headless (mesmo padrão de mobile-drawer.cy.js).
Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })
const NARROW = [320, 375, 767]
const PARTIAL = [768, 1023, 1024]

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('drawer em tela cheia abaixo de 768px na interface em execução', () => {
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

  function openDrawer() {
    cy.visit('/')
    cy.get('button.menu-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'open').and('have.attr', 'role', 'dialog')
    cy.get('.content').should('have.attr', 'inert')
  }

  it('ocupa a tela útil com lock do body em cada largura abaixo de 768px', () => {
    NARROW.forEach((width) => {
      cy.viewport(width, 900)
      openDrawer()
      cy.get('#app-sidebar').then(($sidebar) => {
        const panel = $sidebar[0]
        const rect = panel.getBoundingClientRect()
        const documentElement = panel.ownerDocument.documentElement
        expect(rect.width, `largura útil em ${width}px`).to.equal(documentElement.clientWidth)
        expect(rect.left, `borda esquerda em ${width}px`).to.equal(0)
        expect(rect.top, `borda superior em ${width}px`).to.equal(0)
      })
      cy.get('body')
        .should('have.class', 'drawer-locked')
        .and(($body) => {
          expect(getComputedStyle($body[0]).overflow, `overflow do body em ${width}px`).to.equal('hidden')
        })
      cy.document().then((doc) => {
        expect(doc.documentElement.scrollWidth, `sem overflow em ${width}px`).to.be.lte(
          doc.documentElement.clientWidth,
        )
      })
    })
  })

  it('fecha por botão dedicado, Escape e navegação com foco devolvido em 767px', () => {
    cy.viewport(767, 900)

    openDrawer()
    cy.get('button.sidebar-close').click()
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('body').should('not.have.class', 'drawer-locked')

    openDrawer()
    cy.document().then((doc) => {
      expect(doc.body.classList.contains('drawer-locked'), 'lock antes do Escape').to.equal(true)
    })
    cy.get('body').type('{esc}')
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('body').should('not.have.class', 'drawer-locked')
    cy.document().then((doc) => {
      expect(doc.activeElement.className, 'foco volta para o toggle').to.include('menu-toggle')
    })

    openDrawer()
    cy.get('#app-sidebar .nav a.leaf').first().click()
    cy.url().should('match', /#\/boards\//)
    cy.get('#app-sidebar').should('not.have.class', 'open')
    cy.get('body').should('not.have.class', 'drawer-locked')
  })

  it('mantém alvos de toque de 44px em 767px', () => {
    cy.viewport(767, 900)
    openDrawer()
    cy.get('button.sidebar-close').then(($button) => {
      const rect = $button[0].getBoundingClientRect()
      expect(rect.width, 'largura do botão de fechar').to.equal(44)
      expect(rect.height, 'altura do botão de fechar').to.equal(44)
    })
    cy.get('#app-sidebar .nav-entry, #app-sidebar .leaf').each(($line) => {
      expect($line[0].getBoundingClientRect().height, 'altura da linha').to.be.at.least(44)
    })
  })

  it('mantém o drawer parcial de 200px com scrim e sem lock em 768px a 1024px', () => {
    PARTIAL.forEach((width) => {
      cy.viewport(width, 900)
      openDrawer()
      cy.get('#app-sidebar').then(($sidebar) => {
        expect($sidebar[0].getBoundingClientRect().width, `largura do parcial em ${width}px`).to.equal(200)
      })
      cy.get('button.sidebar-close').should('not.exist')
      cy.get('body').should('not.have.class', 'drawer-locked')
      cy.get('.scrim').should('exist')
      cy.get('.scrim').click()
      cy.get('#app-sidebar').should('not.have.class', 'open')
      cy.get('.scrim').should('not.exist')
    })
  })

  it('mantém a sidebar desktop intacta em 1025px', () => {
    cy.viewport(1025, 900)
    cy.visit('/')
    cy.get('#app-sidebar')
      .should('be.visible')
      .then(($sidebar) => {
        expect($sidebar[0].getBoundingClientRect().width, 'largura da sidebar').to.equal(200)
      })
    cy.get('#app-sidebar').should('not.have.attr', 'role', 'dialog')
    cy.get('button.menu-toggle').should('not.be.visible')
    cy.get('.scrim').should('not.exist')
    cy.document().then((doc) => {
      expect(doc.documentElement.scrollWidth).to.be.lte(doc.documentElement.clientWidth)
    })
  })
})
