const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const PHOTO = 'cypress/fixtures/profile-photo.png'

const hexToRgb = (hex) =>
  `rgb(${parseInt(hex.slice(1, 3), 16)}, ${parseInt(hex.slice(3, 5), 16)}, ${parseInt(hex.slice(5, 7), 16)})`

let initialUser = null

describe('perfil: upload, validação, remoção, save e fallback do avatar (KAN-133)', () => {
  let errors

  before(() => {
    cy.request('GET', '/api/user').then((response) => {
      initialUser = response.body
    })
  })

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => errors.push(`console.error: ${args.join(' ')}`))
    })
    cy.visit('/#/profile')
    cy.get('[data-test="photo-input"]').should('exist')
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
    cy.request('PATCH', '/api/user', {
      displayName: initialUser.displayName,
      avatar: initialUser.avatar,
      avatarColor: initialUser.avatarColor,
    })
  })

  it('upload válido mostra preview imediato, salva e persiste após recarregar', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.get('[data-test="avatar-preview"] img').should('not.exist')
    cy.get('[data-test="photo-input"]').selectFile(PHOTO)
    cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')
    cy.get('[data-test="save"]').should('not.be.disabled').click()
    cy.wait('@patchUser')
    cy.get('[data-test="save-status"], .toast.success').should('contain', 'Saved.')
    cy.reload()
    cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')
  })

  it('o data URL enviado no PATCH é menor que o data URL do arquivo original', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.readFile(PHOTO, 'base64').then((base64) => {
      const originalLength = `data:image/png;base64,${base64}`.length
      cy.get('[data-test="photo-input"]').selectFile(PHOTO)
      cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')
      cy.get('[data-test="save"]').click()
      cy.wait('@patchUser').then((interception) => {
        const sent = interception.request.body.avatar
        expect(sent, 'data URL de imagem').to.match(/^data:image\//)
        expect(sent.length, 'data URL enviado menor que o original').to.be.lessThan(originalLength)
      })
    })
  })

  it('remover + save + recarregar restaura a inicial sobre a cor determinística', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.get('[data-test="photo-input"]').selectFile(PHOTO)
    cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')
    cy.get('[data-test="save"]').click()
    cy.wait('@patchUser')
    cy.reload()
    cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')
    cy.get('[data-test="remove-photo"]').click()
    cy.get('[data-test="avatar-preview"] img').should('not.exist')
    cy.get('[data-test="avatar-preview"]')
      .should('have.attr', 'style')
      .and('contain', hexToRgb(initialUser.avatarColor))
    cy.get('[data-test="avatar-preview"]').invoke('text').should('match', /^[A-Z]$/)
    cy.get('[data-test="save"]').should('not.be.disabled').click()
    cy.wait('@patchUser').then((interception) => {
      expect(interception.request.body.avatar, 'PATCH envia null ao remover').to.be.null
    })
    cy.reload()
    cy.get('[data-test="avatar-preview"] img').should('not.exist')
    cy.get('[data-test="avatar-preview"]').invoke('text').should('match', /^[A-Z]$/)
  })

  it('arquivo inválido e acima de 2 MB recebem mensagem custom, sem alert, preservando a foto', () => {
    cy.window().then((win) => cy.stub(win, 'alert').as('alert'))
    cy.get('[data-test="photo-input"]').selectFile(PHOTO)
    cy.get('[data-test="avatar-preview"] img', { timeout: 15000 }).should('be.visible')

    cy.get('[data-test="photo-input"]').selectFile({
      contents: Cypress.Buffer.from('not an image'),
      fileName: 'notes.txt',
      mimeType: 'text/plain',
    })
    cy.get('.hint.error, .toast.error').should('contain', 'Use a PNG, JPEG or WebP image.')
    cy.get('[data-test="avatar-preview"] img').should('be.visible')
    cy.get('@alert').should('not.have.been.called')

    cy.get('[data-test="photo-input"]').selectFile({
      contents: Cypress.Buffer.alloc(2 * 1024 * 1024 + 1),
      fileName: 'big.png',
      mimeType: 'image/png',
    })
    cy.get('.hint.error, .toast.error').should('contain', 'The image must be at most 2 MB.')
    cy.get('[data-test="avatar-preview"] img').should('be.visible')
    cy.get('@alert').should('not.have.been.called')
  })

  it('Save disabled sem alterações, loading no PATCH e login readonly', () => {
    cy.intercept('PATCH', '/api/user', (request) => {
      request.on('response', (response) => response.setDelay(400))
    }).as('patchUser')
    cy.get('[data-test="save"]').should('be.disabled')
    cy.get('#profile-login').then(($login) => {
      expect($login[0].readOnly || $login[0].disabled, 'login não editável').to.eq(true)
    })

    cy.get('[data-test="photo-input"]').selectFile(PHOTO)
    cy.get('[data-test="save"]').should('not.be.disabled').click()
    cy.get('[data-test="save"]').should('be.disabled')
    cy.contains('Saving').should('be.visible')
    cy.wait('@patchUser')
    cy.get('[data-test="save-status"], .toast.success').should('contain', 'Saved.')
  })
})
