const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const FIXTURE = 'cypress/fixtures/agent-portrait-3x4.png'

const channel = (value) => {
  const normalized = value / 255
  return normalized <= 0.03928 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4
}

const rgb = (value) => value.match(/\d+/g).slice(0, 3).map(Number)

const luminance = (value) => {
  const [r, g, b] = rgb(value).map(channel)
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

const contrast = (foreground, background) => {
  const first = luminance(foreground)
  const second = luminance(background)
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05)
}

let vivy = null

describe('Avatar nas superfícies do agente (KAN-109)', () => {
  let errors

  before(() => {
    cy.request('GET', '/api/agents').then((response) => {
      vivy = response.body.find((agent) => agent.id === 'vivy')
    })
  })

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => errors.push(`console.error: ${args.join(' ')}`))
    })
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
    cy.request('PATCH', '/api/agents/vivy', vivy)
  })

  const openAgents = () => {
    cy.visit('/#/teams/default/agents')
    cy.get('.agent-card').should('have.length.at.least', 1)
  }

  const openVivy = () => {
    cy.contains('.agent-card', 'Vivy').find('button[title="Edit"]').click()
    cy.get('.modal-card').should('be.visible')
  }

  it('lista com avatar circular 64x64, recorte cover e proporção 3x4', () => {
    openAgents()
    cy.contains('.agent-card', 'Vivy')
      .find('.avatar.lg')
      .then(($avatar) => {
        const element = $avatar[0]
        const style = getComputedStyle(element)
        expect(element.getBoundingClientRect().width).to.be.closeTo(64, 1)
        expect(element.getBoundingClientRect().height).to.be.closeTo(64, 1)
        expect(style.borderRadius).to.eq('50%')
        const image = element.querySelector('img')
        expect(image, 'imagem do seed').to.exist
        expect(getComputedStyle(image).objectFit).to.eq('cover')
        const ratio = image.naturalWidth / image.naturalHeight
        expect(ratio, 'proporção natural 3x4').to.be.closeTo(0.75, 0.02)
      })
  })

  it('edita Vivy: preview do seed, fixture 3x4 antes do save e lista refletindo', () => {
    openAgents()
    openVivy()
    cy.get('.agent-preview .avatar.lg img').should('exist')
    cy.get('.agent-preview .avatar.lg img').invoke('attr', 'src').then((before) => {
      cy.get('.modal-card input[type="file"]').selectFile(FIXTURE)
      cy.get('.agent-preview .avatar.lg img')
        .invoke('attr', 'src')
        .should('not.eq', before)
        .and('match', /^data:image\//)
      cy.get('.modal-footer').contains('button', 'Save').click()
      cy.get('.modal-card').should('not.exist')
      cy.contains('.agent-card', 'Vivy')
        .find('.avatar.lg img')
        .invoke('attr', 'src')
        .should('not.eq', before)
    })
  })

  it('painel da issue mostra o avatar md 42x42 circular com a foto', () => {
    openAgents()
    openVivy()
    cy.get('.modal-card input[type="file"]').selectFile(FIXTURE)
    cy.get('.modal-footer').contains('button', 'Save').click()
    cy.visit('/')
    cy.contains('.card', 'KANB-1').click()
    cy.get('.panel-meta .avatar.md').then(($avatar) => {
      const element = $avatar[0]
      expect(element.getBoundingClientRect().width).to.be.closeTo(42, 1)
      expect(element.getBoundingClientRect().height).to.be.closeTo(42, 1)
      expect(getComputedStyle(element).borderRadius).to.eq('50%')
      expect(element.querySelector('img'), 'foto no painel').to.exist
    })
  })

  it('Remove photo limpa o preview e o save volta à inicial com contraste', () => {
    openAgents()
    openVivy()
    cy.get('.modal-card').contains('button', 'Remove photo').click()
    cy.get('.agent-preview .avatar.lg img').should('not.exist')
    cy.get('.modal-footer').contains('button', 'Save').click()
    cy.get('.modal-card').should('not.exist')
    cy.contains('.agent-card', 'Vivy')
      .find('.avatar.lg')
      .then(($avatar) => {
        const element = $avatar[0]
        const style = getComputedStyle(element)
        expect(element.querySelector('img')).to.be.null
        expect(element.textContent.trim()).to.eq('V')
        expect(contrast(style.color, style.backgroundColor)).to.be.at.least(4.5)
      })
  })

  it('tipo inválido e arquivo acima de 2 MB preservam a foto e mostram mensagem', () => {
    openAgents()
    openVivy()
    cy.window().then((win) => cy.stub(win, 'alert').as('alert'))
    cy.get('.agent-preview .avatar.lg img').invoke('attr', 'src').then((before) => {
      cy.get('.modal-card input[type="file"]').selectFile({
        contents: Cypress.Buffer.from('texto'),
        fileName: 'nota.txt',
        mimeType: 'text/plain',
      })
      cy.get('.modal-card .error').should('contain', 'Use a PNG, JPEG or WebP image.')
      cy.get('.agent-preview .avatar.lg img').invoke('attr', 'src').should('eq', before)

      cy.get('.modal-card input[type="file"]').selectFile({
        contents: Cypress.Buffer.alloc(2 * 1024 * 1024 + 1),
        fileName: 'grande.png',
        mimeType: 'image/png',
      })
      cy.get('.modal-card .error').should('contain', 'The image must be at most 2 MB.')
      cy.get('.agent-preview .avatar.lg img').invoke('attr', 'src').should('eq', before)
      cy.get('@alert').should('not.have.been.called')
    })
  })

  it('rótulo Portrait não carrega mais o sufixo (3x4)', () => {
    openAgents()
    openVivy()
    cy.get('.modal-card label').contains('Portrait').should('not.contain.text', '(3x4)').and('have.text', 'Portrait')
  })
})
