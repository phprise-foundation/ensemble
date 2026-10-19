Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

describe('painel do copilot (grade direita) na interface em execução', () => {
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

  it('nasce expandido com largura min(25vw, 400px) e símbolo no menu superior', () => {
    cy.viewport(1280, 900)
    cy.visit('/')
    cy.window().then((win) => {
      const esperado = Math.min(win.innerWidth * 0.25, 400)
      cy.get('#app-copilot').should('be.visible')
      cy.get('#app-copilot').then(($painel) => {
        expect($painel[0].getBoundingClientRect().width).to.be.closeTo(esperado, 1)
      })
      cy.get('button.copilot-toggle').should('be.visible')
      cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')
      cy.get('button.copilot-toggle').should('have.attr', 'aria-controls', 'app-copilot')
      cy.get('button.copilot-toggle').should('have.attr', 'aria-label', 'Toggle copilot')
    })
  })

  it('nasce expandido com largura máxima de 400px em 1920x900', () => {
    cy.viewport(1920, 900)
    cy.visit('/')
    cy.window().then((win) => {
      const esperado = Math.min(win.innerWidth * 0.25, 400)
      cy.get('#app-copilot').should('be.visible')
      cy.get('#app-copilot').then(($painel) => {
        expect($painel[0].getBoundingClientRect().width).to.be.closeTo(esperado, 1)
        expect($painel[0].getBoundingClientRect().width).to.equal(400)
      })
    })
  })

  it('clicar no símbolo colapsa e clicar de novo expande, sem tocar no sidebar', () => {
    cy.viewport(1280, 900)
    cy.visit('/')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'false')

    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('be.visible')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')

    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
  })

  it('o atalho Ctrl+Alt+B alterna o copilot e não colapsa o sidebar', () => {
    cy.viewport(1280, 900)
    cy.visit('/')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('body').type('{ctrl+alt+b}')
    cy.get('#app-copilot').should('not.exist')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('body').type('{ctrl+alt+b}')
    cy.get('#app-copilot').should('be.visible')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('body').type('{ctrl+b}')
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('#app-copilot').should('be.visible')
  })

  it('no breakpoint (1024x900, lado mobile) nem painel nem símbolo existem no DOM', () => {
    cy.viewport(1024, 900)
    cy.visit('/')
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('not.exist')
  })

  it('recarregar a página volta com o painel expandido (sem memória)', () => {
    cy.viewport(1280, 900)
    cy.visit('/')
    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('not.exist')

    cy.reload()
    cy.get('#app-copilot').should('be.visible')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')
  })
})
