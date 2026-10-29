const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const unique = (prefix) => `${prefix} ${Date.now()}`

const field = (label) =>
  cy.get('.modal-card .field').filter((_, element) => element.querySelector('span')?.textContent.trim() === label)

describe('secrets and repository credentials', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => collectErrors(win, errors))
    cy.viewport(1400, 900)
  })

  it('creates, rotates and deletes a secret without exposing the value', () => {
    const name = unique('E2E PAT')
    const value = 'ghp_e2e_original_value'
    cy.intercept('POST', '**/api/workspaces/*/secrets').as('createSecret')
    cy.intercept('PATCH', '**/api/workspaces/*/secrets/*').as('rotateSecret')
    cy.intercept('DELETE', '**/api/workspaces/*/secrets/*').as('deleteSecret')

    cy.visitApp('/#/secrets')
    cy.contains('h2', 'Secrets').should('be.visible')
    cy.contains('button', 'New secret').click()
    field('Name').find('input').type(name)
    field('Value').find('input').type(value)
    cy.get('.modal-footer').contains('button', 'Save').click()
    cy.wait('@createSecret').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.value).to.eq(value)
    })
    cy.get('.modal-card').should('not.exist')
    cy.contains('.table-row', name).should('exist')
    cy.contains(value).should('not.exist')

    cy.contains('.table-row', name).find('button[aria-label="Rotate"]').click()
    field('New value').find('input').type('ghp_e2e_rotated_value')
    cy.get('.modal-footer').contains('button', 'Save').click()
    cy.wait('@rotateSecret').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(200)
      expect(call.request.body.value).to.eq('ghp_e2e_rotated_value')
    })
    cy.contains('.table-row', name).should('exist')
    cy.contains('ghp_e2e_rotated_value').should('not.exist')

    cy.contains('.table-row', name).find('button[aria-label="Delete"]').click()
    cy.get('.dialog-confirm').click()
    cy.wait('@deleteSecret').its('response.statusCode').should('eq', 200)
    cy.contains('.table-row', name).should('not.exist')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('attaches a secret to a repository and tests the connection', () => {
    const name = unique('E2E repo')
    const secretName = unique('E2E repo credential')
    let secretId = null
    let repositoryId = null

    cy.wsRequest('POST', '/secrets', { name: secretName, value: 'ghp_e2e_repo_value' }).then((created) => {
      secretId = created.body.secret.id
      cy.wrap(secretId).as('secretId')
    })

    cy.intercept('POST', '**/api/workspaces/*/repositories').as('createRepository')
    cy.visitApp('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button[title="New source"]').click({ force: true })
    field('Name').find('input').type(name)
    field('HTTPS URL').find('input').type('https://github.com/example/e2e-credentials.git')
    cy.get('@secretId').then((id) => {
      field('Credential (optional)').find('select').select(id)
    })
    cy.get('.modal-footer').contains('button', 'Save').click()
    cy.wait('@createRepository').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.secretId).to.eq(secretId)
      repositoryId = call.response.body.repository.id
    })
    cy.get('.modal-card').should('not.exist')

    let probeResult = { ok: false, message: 'bad credentials' }
    cy.intercept('POST', '**/api/workspaces/*/repositories/test', (request) => {
      request.reply({ statusCode: 200, body: probeResult })
    }).as('probe')
    cy.contains('.nav-entry', name).find('button[title="Edit source"]').click({ force: true })
    cy.get('@secretId').then((id) => {
      field('Credential (optional)').find('select').should('have.value', id)
    })
    cy.contains('.modal-card button', 'Test connection').click()
    cy.wait('@probe')
    cy.get('.modal-card .error').should('contain', 'bad credentials')

    cy.then(() => {
      probeResult = { ok: true, message: 'connection ok' }
    })
    cy.contains('.modal-card button', 'Test connection').click()
    cy.wait('@probe')
    cy.get('.toast').should('contain', 'Connection OK.')
    cy.contains('.modal-card button', 'Cancel').click()

    cy.then(() => {
      if (repositoryId) {
        cy.wsRequest('DELETE', `/repositories/${repositoryId}`, undefined, { failOnStatusCode: false })
      }
      cy.wsRequest('DELETE', `/secrets/${secretId}`, undefined, { failOnStatusCode: false })
    })
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })
})
