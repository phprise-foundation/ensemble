Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const hexToRgb = (hex) => {
  const value = hex.replace('#', '')
  return `rgb(${parseInt(value.slice(0, 2), 16)}, ${parseInt(value.slice(2, 4), 16)}, ${parseInt(value.slice(4, 6), 16)})`
}

const attachFile = ($input, file) => {
  const transfer = new DataTransfer()
  transfer.items.add(file)
  $input[0].files = transfer.files
  $input[0].dispatchEvent(new Event('change', { bubbles: true }))
}

const OVERFLOW_BYTES = 2 * 1024 * 1024 + 1

function preparePhoto(base64) {
  return cy
    .visit('/#/profile')
    .window()
    .then((win) =>
      win
        .fetch(`data:image/png;base64,${base64}`)
        .then((response) => response.blob())
        .then((blob) => {
          const file = new win.File([blob], 'profile-photo.png', { type: 'image/png' })
          const originalDataUrl = new win.Promise((resolve, reject) => {
            const reader = new win.FileReader()
            reader.onload = () => resolve(String(reader.result))
            reader.onerror = () => reject(new Error('fixture ilegível'))
            reader.readAsDataURL(blob)
          }).then((raw) => {
            preparePhoto.context = { win, file, originalDataUrl: raw }
          })
          return originalDataUrl
        }),
    )
}

describe('perfil: foto do usuário — upload, validação, remoção, save e fallback (KAN-126)', () => {
  let errors
  let initialUser

  before(() => {
    cy.request('GET', '/api/user').its('body').then((user) => {
      initialUser = user
    })
  })

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      win.alert = () => errors.push('alert nativo chamado')
      win.confirm = () => {
        errors.push('confirm nativo chamado')
        return true
      }
      win.prompt = () => {
        errors.push('prompt nativo chamado')
        return ''
      }
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.request('PATCH', '/api/user', { displayName: '', avatar: null })
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.request('PATCH', '/api/user', {
      displayName: initialUser.displayName,
      avatar: initialUser.avatar ?? null,
    })
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('faz upload, mostra preview circular, salva com feedback e persiste após reload', () => {
    let sentAvatarLength = 0
    cy.intercept('PATCH', '/api/user', (request) => {
      sentAvatarLength = String(request.body?.avatar ?? '').length
      request.reply((response) => {
        response.setDelay(400)
        response.send()
      })
    }).as('patchUser')

    cy.fixture('profile-photo.png', 'base64').then((base64) => {
      preparePhoto(base64)
      cy.then(() => {
        expect(preparePhoto.context.originalDataUrl.length, 'data URL original da fixture').to.be.greaterThan(
          500 * 1024,
        )
      })
      cy.get('[data-test="save"]').should('be.disabled')
      cy.get('[data-test="avatar-preview"] img').should('not.exist')
      cy.get('[data-test="photo-input"]').then(($input) => attachFile($input, preparePhoto.context.file))
      cy.get('[data-test="avatar-preview"] img')
        .should('be.visible')
        .and('have.attr', 'src')
        .and('match', /^data:image\/(webp|jpeg|png);base64,/)
      cy.get('[data-test="avatar-preview"].avatar').then(($preview) => {
        expect(getComputedStyle($preview[0]).borderRadius, 'preview circular').to.eq('50%')
      })
      cy.get('[data-test="save"]').should('be.enabled').click()
      cy.get('[role="status"]', { timeout: 2000 }).should('be.visible')
      cy.wait('@patchUser').then(() => {
        expect(sentAvatarLength, 'data URL enviado menor que o original').to.be.lessThan(
          preparePhoto.context.originalDataUrl.length,
        )
      })
      cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
      cy.reload()
      cy.get('[data-test="avatar-preview"] img').should('be.visible')
      cy.get('.topbar .profile-avatar img').should('be.visible')
      cy.get('[data-test="save"]').should('be.disabled')
    })
  })

  it('remove a foto, salva e volta ao fallback inicial sobre a cor determinística', () => {
    cy.fixture('profile-photo.png', 'base64').then((base64) => {
      cy.request('GET', '/api/user')
        .its('body')
        .then((user) => {
          preparePhoto(base64)
          cy.get('[data-test="photo-input"]').then(($input) => attachFile($input, preparePhoto.context.file))
          cy.get('[data-test="save"]').should('be.enabled').click()
          cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
          cy.reload()
          cy.get('[data-test="avatar-preview"] img').should('be.visible')
          cy.get('[data-test="remove-photo"]').click()
          cy.get('[data-test="avatar-preview"] img').should('not.exist')
          cy.get('[data-test="avatar-preview"]')
            .should('contain.text', 'A')
            .and('have.attr', 'aria-label', 'Avatar de admin')
          cy.get('[data-test="avatar-preview"].avatar').then(($preview) => {
            expect($preview[0].style.backgroundColor, 'cor determinística do login').to.eq(
              hexToRgb(user.avatarColor),
            )
          })
          cy.get('[data-test="save"]').click()
          cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
          cy.reload()
          cy.get('[data-test="avatar-preview"] img').should('not.exist')
          cy.get('.topbar .profile-avatar img').should('not.exist')
          cy.get('.topbar .profile-avatar').should('contain.text', 'A')
          cy.get('[data-test="avatar-preview"].avatar').then(($preview) => {
            expect($preview[0].style.backgroundColor, 'cor preservada no fallback').to.eq(
              hexToRgb(user.avatarColor),
            )
          })
        })
    })
  })

  it('rejeita arquivo de tipo inválido com toast custom e preserva a foto em rascunho', () => {
    cy.fixture('profile-photo.png', 'base64').then((base64) => {
      preparePhoto(base64)
      cy.window().then((win) => {
        preparePhoto.context.invalidFile = new win.File(
          [new win.Blob(['not an image'], { type: 'text/plain' })],
          'notes.txt',
          { type: 'text/plain' },
        )
      })
      cy.get('[data-test="photo-input"]').then(($input) => attachFile($input, preparePhoto.context.file))
      cy.get('[data-test="avatar-preview"] img')
        .should('be.visible')
        .then(($photo) => {
          const src = $photo.attr('src')
          cy.get('[data-test="photo-input"]').then(($again) =>
            attachFile($again, preparePhoto.context.invalidFile),
          )
          cy.get('.toast-text').should('contain.text', 'Use a PNG, JPEG or WebP image.')
          cy.get('[data-test="avatar-preview"] img').should('have.attr', 'src', src)
        })
    })
  })

  it('rejeita arquivo acima de 2 MB com toast custom e sem alert nativo', () => {
    cy.visit('/#/profile')
      .window()
      .then((win) => {
        const oversized = new win.File([new win.Uint8Array(OVERFLOW_BYTES)], 'huge.png', {
          type: 'image/png',
        })
        expect(oversized.size, 'arquivo acima do limite').to.be.greaterThan(2 * 1024 * 1024)
        return oversized
      })
      .then((file) => {
        cy.get('[data-test="photo-input"]').then(($input) => attachFile($input, file))
        cy.get('.toast-text').should('contain.text', 'The image must be at most 2 MB.')
        cy.get('[data-test="avatar-preview"] img').should('not.exist')
      })
  })

  it('desabilita o Save sem alterações, mantém o login readonly e mostra loading durante o PATCH', () => {
    cy.intercept('PATCH', '/api/user', (request) => {
      request.reply((response) => {
        response.setDelay(700)
        response.send()
      })
    }).as('patchUser')

    cy.visit('/#/profile')
    cy.get('[data-test="save"]').should('be.disabled')
    cy.get('#profile-login').then(($login) => {
      expect($login[0].readOnly || $login[0].disabled, 'login não editável').to.eq(true)
    })
    cy.get('#profile-name').clear().type('Ian the Coder')
    cy.get('[data-test="save"]').should('be.enabled').click()
    cy.get('[role="status"]', { timeout: 2000 }).should('contain.text', 'Saving…')
    cy.wait('@patchUser')
    cy.get('.toast-text', { timeout: 2000 }).should('contain.text', 'Saved.')
    cy.get('#profile-name').should('have.value', 'Ian the Coder')
    cy.get('[data-test="save"]').should('be.disabled')
  })
})
