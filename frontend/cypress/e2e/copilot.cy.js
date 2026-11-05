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

  it('nasce fechado e abre pelo símbolo do menu com largura min(25vw, 400px)', () => {
    cy.viewport(1280, 900)
    cy.visitApp('/')
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('be.visible').click()

    cy.window().then((win) => {
      const esperado = Math.min(win.innerWidth * 0.25, 400)
      cy.get('#app-copilot').should('be.visible')
      cy.get('#app-copilot').then(($painel) => {
        expect($painel[0].getBoundingClientRect().width).to.be.closeTo(esperado, 1)
      })
      cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')
      cy.get('button.copilot-toggle').should('have.attr', 'aria-controls', 'app-copilot')
      cy.get('button.copilot-toggle').should('have.attr', 'aria-label', 'Toggle copilot')
    })
  })

  it('abre com largura máxima de 400px em 1920x900', () => {
    cy.viewport(1920, 900)
    cy.visitApp('/')
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').click()

    cy.window().then((win) => {
      const esperado = Math.min(win.innerWidth * 0.25, 400)
      cy.get('#app-copilot').should('be.visible')
      cy.get('#app-copilot').then(($painel) => {
        expect($painel[0].getBoundingClientRect().width).to.be.closeTo(esperado, 1)
        expect($painel[0].getBoundingClientRect().width).to.equal(400)
      })
    })
  })

  it('clicar no símbolo abre e clicar de novo fecha, sem tocar no sidebar', () => {
    cy.viewport(1280, 900)
    cy.visitApp('/')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
    cy.get('#app-copilot').should('not.exist')

    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('be.visible')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')

    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'false')

    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
  })

  it('o atalho Ctrl+Alt+B abre e fecha o copilot e não colapsa o sidebar', () => {
    cy.viewport(1280, 900)
    cy.visitApp('/')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
    cy.get('#app-copilot').should('not.exist')

    cy.get('body').type('{ctrl+alt+b}')
    cy.get('#app-copilot').should('be.visible')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('body').type('{ctrl+alt+b}')
    cy.get('#app-copilot').should('not.exist')
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')

    cy.get('body').type('{ctrl+b}')
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('#app-copilot').should('not.exist')
  })

  it('abrir um issue e alternar o copilot não altera o painel de issue nem as colunas', () => {
    cy.viewport(1280, 900)
    cy.visitApp('/')
    cy.get('.card').should('have.length.at.least', 1)
    cy.get('.card').first().click()
    cy.get('.issue-panel').should('be.visible')
    cy.get('.issue-panel .panel-title').invoke('text').then((titulo) => {
      cy.get('.column').then(($colunas) => {
        const antes = $colunas.length

        cy.get('button.copilot-toggle').click()
        cy.get('#app-copilot').should('be.visible')
        cy.get('.issue-panel').should('be.visible')
        cy.get('.issue-panel .panel-title').should('have.text', titulo)
        cy.get('.column').should('have.length', antes)

        cy.get('body').type('{ctrl+alt+b}')
        cy.get('#app-copilot').should('not.exist')
        cy.get('.issue-panel').should('be.visible')
        cy.get('.issue-panel .panel-title').should('have.text', titulo)
        cy.get('.column').should('have.length', antes)
        cy.get('#app-sidebar').should('not.have.class', 'collapsed')
      })
    })
  })

  it('no breakpoint (1024x900, lado mobile) nem painel nem símbolo existem no DOM', () => {
    cy.viewport(1024, 900)
    cy.visitApp('/')
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('not.exist')
  })

  it('em viewport de telefone (390x844) nem painel nem símbolo existem no DOM', () => {
    cy.viewport(390, 844)
    cy.visitApp('/')
    cy.get('#app-copilot').should('not.exist')
    cy.get('button.copilot-toggle').should('not.exist')
  })

  it('recarregar a página mantém a escolha do painel (persistida)', () => {
    cy.viewport(1280, 900)
    cy.visitApp('/')
    cy.get('#app-copilot').should('not.exist')

    cy.get('button.copilot-toggle').click()
    cy.get('#app-copilot').should('be.visible')

    cy.reload()
    cy.get('#app-copilot').should('be.visible')
    cy.get('button.copilot-toggle').should('have.attr', 'aria-expanded', 'true')
  })
})
