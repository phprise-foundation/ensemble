Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const PHOTO = 'cypress/fixtures/profile-photo.png'
const OVERSIZE_BYTES = 2 * 1024 * 1024 + 1

const preview = () => cy.get('[data-test="avatar-preview"]')
const saveButton = () => cy.get('[data-test="save"]')
const upload = (file) => cy.get('[data-test="photo-input"]').selectFile(file)
const previewImage = () => preview().find('img', { timeout: 20000 })

const hexToRgb = (hex) =>
  `rgb(${parseInt(hex.slice(1, 3), 16)}, ${parseInt(hex.slice(3, 5), 16)}, ${parseInt(hex.slice(5, 7), 16)})`

describe('perfil: foto do usuário, save com estados e remoção do voltar (KAN-133)', () => {
  let errors
  let initialUser

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
    preview().should('be.visible')
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

  it('upload válido mostra preview circular imediato e persiste após o Save e o reload', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    previewImage().should('not.exist')
    upload(PHOTO)
    previewImage().should('be.visible')
    preview().then(($avatar) => {
      expect(getComputedStyle($avatar[0]).borderRadius, 'preview circular').to.eq('50%')
    })
    saveButton().should('not.be.disabled').click()
    cy.wait('@patchUser').its('response.statusCode').should('eq', 200)
    cy.get('.toast-text').should('contain', 'Saved.')
    cy.reload()
    previewImage().should('be.visible')
  })

  it('o data URL enviado no PATCH é menor que o do arquivo original', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.readFile(PHOTO, 'base64').then((base64) => {
      const originalLength = `data:image/png;base64,${base64}`.length
      upload(PHOTO)
      previewImage().should('be.visible')
      saveButton().click()
      cy.wait('@patchUser').then(({ request }) => {
        const sent = request.body.avatar
        expect(sent, 'data URL de imagem').to.match(/^data:image\/(png|jpeg|webp);base64,/)
        expect(sent.length, 'data URL enviado menor que o original').to.be.lessThan(originalLength)
      })
    })
  })

  it('Remover + Save restaura o fallback: inicial sobre a cor persistida', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    upload(PHOTO)
    previewImage().should('be.visible')
    saveButton().click()
    cy.wait('@patchUser')
    cy.reload()
    previewImage().should('be.visible')

    saveButton().should('be.disabled')
    cy.get('[data-test="remove-photo"]').click()
    previewImage().should('not.exist')
    preview().should('have.css', 'background-color', hexToRgb(initialUser.avatarColor))
    preview().invoke('text').invoke('trim').should('match', /^[A-Z]$/)
    saveButton().should('not.be.disabled').click()
    cy.wait('@patchUser').then(({ request }) => {
      expect(request.body.avatar, 'PATCH de remoção envia null').to.be.null
    })
    cy.reload()
    previewImage().should('not.exist')
    preview().should('have.css', 'background-color', hexToRgb(initialUser.avatarColor))
    preview().invoke('text').invoke('trim').should('match', /^[A-Z]$/)
  })

  it('arquivo inválido e acima de 2 MB recebem feedback custom e preservam a foto', () => {
    cy.window().then((win) => {
      cy.stub(win, 'alert').as('alert')
      cy.stub(win, 'confirm').as('confirm')
    })
    upload(PHOTO)
    previewImage().should('be.visible')

    upload({ contents: Cypress.Buffer.from('nao e uma imagem'), fileName: 'notes.txt', mimeType: 'text/plain' })
    cy.get('.hint.error, .toast.error', { timeout: 12000 }).should(
      'contain',
      'Use a PNG, JPEG or WebP image.',
    )
    cy.get('@alert').should('not.have.been.called')
    cy.get('@confirm').should('not.have.been.called')
    previewImage().should('be.visible')

    cy.get('[data-test="photo-input"]').selectFile(
      { contents: Cypress.Buffer.alloc(OVERSIZE_BYTES), fileName: 'grande.png', mimeType: 'image/png' },
      { force: true },
    )
    cy.get('.hint.error, .toast.error', { timeout: 12000 }).should(
      'contain',
      'The image must be at most 2 MB.',
    )
    cy.get('@alert').should('not.have.been.called')
    cy.get('@confirm').should('not.have.been.called')
    previewImage().should('be.visible')
  })

  it('Save começa disabled, entra em loading durante o PATCH e o login é somente leitura', () => {
    cy.intercept('PATCH', '/api/user', (request) => {
      request.on('response', (response) => response.setDelay(400))
    }).as('patchUser')
    saveButton().should('be.disabled')
    cy.get('#profile-login').should('have.attr', 'readonly')
    cy.get('[data-test="display-name"]').clear().type('Maria Fernanda')
    saveButton().should('not.be.disabled').click()
    saveButton().should('be.disabled')
    cy.get('[role="status"]').should('contain', 'Saving')
    cy.wait('@patchUser')
    cy.get('.toast-text').should('contain', 'Saved.')
    saveButton().should('be.disabled')
  })

  it('sair com pendências pede o confirm custom com foco no cancelar e descarta o rascunho', () => {
    cy.window().then((win) => {
      cy.stub(win, 'confirm').as('nativeConfirm')
      cy.stub(win, 'alert').as('nativeAlert')
    })
    upload(PHOTO)
    previewImage().should('be.visible')
    cy.get('[data-test="display-name"]').clear().type('Maria Fernanda')
    cy.get('.submenu a.leaf').first().click({ force: true })
    cy.get('.dialog-card').should('be.visible')
    cy.get('#dialog-title').should('have.text', 'Discard changes?')
    cy.focused().should('have.class', 'dialog-cancel')
    cy.get('@nativeConfirm').should('not.have.been.called')
    cy.get('@nativeAlert').should('not.have.been.called')
    cy.get('.dialog-confirm').click()
    cy.url().should('not.contain', '/profile')
    cy.visit('/#/profile')
    previewImage().should('not.exist')
    cy.get('[data-test="display-name"]').should('have.value', initialUser.displayName)
  })
})
