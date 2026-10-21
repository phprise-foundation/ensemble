Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const PHOTO_FIXTURE = 'cypress/fixtures/profile-photo.png'
const OVERSIZE_FIXTURE = 'cypress/fixtures/photo-oversize.png'
const INVALID_FIXTURE = 'cypress/fixtures/photo-invalid.txt'

let initialState = null

describe('perfil: upload, validação, remoção, save e fallback do avatar (KAN-133)', () => {
  let errors

  before(() => {
    cy.request('GET', '/api/user').then((response) => {
      initialState = response.body
    })
  })

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.visit('/#/profile')
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
    cy.request('PATCH', '/api/user', {
      displayName: initialState.displayName,
      avatar: initialState.avatar,
      avatarColor: initialState.avatarColor,
    })
  })

  it('upload válido mostra preview circular imediato, salva e persiste após recarregar', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.get('[data-test="avatar-preview"]').find('img').should('not.exist')
    cy.get('[data-test="photo-input"]').selectFile(PHOTO_FIXTURE)
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')
    cy.get('[data-test="save"]').should('not.be.disabled').click()
    cy.wait('@patchUser')
    cy.get('.toast.success .toast-text').should('contain', 'Saved.')
    cy.reload()
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')
  })

  it('o data URL enviado no PATCH é menor que o data URL do arquivo original', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.readFile(PHOTO_FIXTURE, 'base64').then((base64) => {
      const originalLength = `data:image/png;base64,${base64}`.length
      cy.get('[data-test="photo-input"]').selectFile(PHOTO_FIXTURE)
      cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')
      cy.get('[data-test="save"]').click()
      cy.wait('@patchUser').then((interception) => {
        const sentAvatar = interception.request.body.avatar
        expect(sentAvatar, 'data URL enviado é um data URL de imagem').to.match(/^data:image\//)
        expect(
          sentAvatar.length,
          'data URL enviado menor que o data URL do arquivo original',
        ).to.be.lessThan(originalLength)
      })
    })
  })

  it('remover + save + recarregar restaura o fallback: inicial maiúscula sobre a cor determinística', () => {
    cy.intercept('PATCH', '/api/user').as('patchUser')
    cy.get('[data-test="photo-input"]').selectFile(PHOTO_FIXTURE)
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')
    cy.get('[data-test="save"]').click()
    cy.wait('@patchUser')
    cy.reload()
    cy.get('[data-test="remove-photo"]').click()
    cy.then(() =>
      cy.document().then((doc) =>
        cy.wrap(doc.querySelector('[data-test="avatar-preview"]').outerHTML).should((h) =>
          expect(h, 'html do preview').to.eq('SENTINEL:' + h),
        ),
      ),
    )
    cy.get('[data-test="avatar-preview"]').find('img').should('not.exist')
    cy.get('[data-test="avatar-preview"]')
      .should('have.attr', 'style')
      .and('contain', initialState.avatarColor)
    cy.get('[data-test="save"]').should('not.be.disabled').click()
    cy.wait('@patchUser').then((interception) => {
      expect(interception.request.body.avatar, 'PATCH envia null ao remover').to.be.null
    })
    cy.reload()
    cy.get('[data-test="avatar-preview"]')
      .should('have.attr', 'style')
      .and('contain', initialState.avatarColor)
    cy.get('[data-test="avatar-preview"]')
      .invoke('text')
      .should('match', /^[A-Z]$/)
    cy.get('[data-test="avatar-preview"]').find('img').should('not.exist')
  })

  it('arquivo inválido e acima de 2 MB recebem mensagem custom e a foto é preservada', () => {
    cy.window().then((win) => cy.stub(win, 'alert').as('alert'))
    cy.get('[data-test="photo-input"]').selectFile(PHOTO_FIXTURE)
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')

    cy.get('[data-test="photo-input"]').selectFile(INVALID_FIXTURE)
    cy.get('.toast.error .toast-text')
      .should('be.visible')
      .and('contain', 'Use a PNG, JPEG or WebP image.')
    cy.get('@alert').should('not.have.been.called')
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')

    cy.get('[data-test="photo-input"]').selectFile(OVERSIZE_FIXTURE)
    cy.get('.toast.error .toast-text')
      .should('be.visible')
      .and('contain', 'The image must be at most 2 MB.')
    cy.get('@alert').should('not.have.been.called')
    cy.get('[data-test="avatar-preview"]').find('img').should('be.visible')
  })

  it('save começa disabled sem alterações, entra em loading durante o PATCH e o login é readonly', () => {
    cy.intercept('PATCH', '/api/user', { delay: 300 }).as('patchUser')
    cy.get('[data-test="save"]').should('be.disabled')
    cy.get('#profile-login').then(($login) => {
      expect($login[0].readOnly || $login[0].disabled, 'login não editável').to.eq(true)
    })
    cy.get('[data-test="photo-input"]').selectFile(PHOTO_FIXTURE)
    cy.get('[data-test="save"]').click()
    cy.get('[data-test="save"]').should('be.disabled')
    cy.get('[role="status"]').should('contain', 'Saving')
    cy.wait('@patchUser')
    cy.get('[role="status"]').should('not.exist')
    cy.get('.toast.success .toast-text').should('contain', 'Saved.')
  })
})
