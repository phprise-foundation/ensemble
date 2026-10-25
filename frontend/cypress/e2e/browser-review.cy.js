const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const unique = (prefix) => `${prefix} ${Date.now()}`

const field = (label) =>
  cy.get('.modal-card .field').filter((_, element) => element.querySelector('span')?.textContent.trim() === label)

describe('browser review: editors, columns and history', () => {
  let errors
  let kanban
  let workflowId
  let originalWorkspaceName
  let initialColumns = []
  let todoColumn
  const created = { boards: [], columns: [], issueTypes: [], statuses: [], transitions: [], repositories: [], fields: [] }

  before(() => {
    cy.wsSnapshot().then((snapshot) => {
      kanban = snapshot.boards.find((board) => board.name === 'Kanban')
      todoColumn = snapshot.columns.find((column) => column.boardId === kanban.id && column.name === 'Todo')
      workflowId = snapshot.workflows[0].id
      originalWorkspaceName = snapshot.workspaces[0].name
      initialColumns = snapshot.columns
        .filter((column) => column.boardId === kanban.id)
        .map((column) => ({ id: column.id, position: column.position }))
    })
  })

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => collectErrors(win, errors))
    cy.viewport(1400, 900)
  })

  afterEach(() => {
    for (const id of created.fields.splice(0)) {
      cy.wsRequest('DELETE', `/fields/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.repositories.splice(0)) {
      cy.wsRequest('DELETE', `/repositories/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.transitions.splice(0)) {
      cy.wsRequest('DELETE', `/transitions/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.statuses.splice(0)) {
      cy.wsRequest('DELETE', `/statuses/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.issueTypes.splice(0)) {
      cy.wsRequest('DELETE', `/issue-types/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.columns.splice(0)) {
      cy.wsRequest('DELETE', `/columns/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const id of created.boards.splice(0)) {
      cy.wsRequest('DELETE', `/boards/${id}`, undefined, { failOnStatusCode: false })
    }
    for (const column of initialColumns) {
      cy.wsRequest('PATCH', `/columns/${column.id}`, { position: column.position }, { failOnStatusCode: false })
    }
    if (todoColumn) {
      cy.wsRequest('PATCH', `/columns/${todoColumn.id}`, { statusIds: todoColumn.statusIds }, { failOnStatusCode: false })
    }
    cy.wsSnapshot().then((snapshot) => {
      if (snapshot.workspaces[0].name !== originalWorkspaceName) {
        cy.e2eContext().then(({ token }) => {
          cy.request({
            method: 'PATCH',
            url: `/api/workspaces/${snapshot.workspaces[0].id}`,
            body: { name: originalWorkspaceName },
            headers: { Authorization: `Bearer ${token}` },
          })
        })
      }
    })
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('creates and edits a board and shows its key, not the hex id, in the sidebar', () => {
    const name = unique('Review board')
    cy.intercept('POST', '**/api/workspaces/*/boards').as('createBoard')
    cy.intercept('PATCH', '**/api/workspaces/*/boards/*').as('updateBoard')

    cy.visitApp('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button[title="New board"]').click({ force: true })
    cy.get('#board-editor-title').should('contain', 'New board')
    cy.get('.modal-card input[type="text"]').first().type(name)
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@createBoard').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      created.boards.push(call.response.body.board.id)
    })

    cy.contains('.leaf', name).should('exist')
    cy.contains('.leaf', name)
      .find('.badge-id')
      .invoke('text')
      .then((badge) => {
        expect(badge).to.eq(name.toLowerCase().replace(/\s+/g, '-'))
      })

    cy.contains('.nav-entry', name).find('button[title="Edit board"]').click({ force: true })
    cy.get('#board-editor-title').should('contain', 'Edit board')
    cy.get('.modal-card input[type="text"]').first().clear().type(`${name} renamed`)
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@updateBoard').its('response.statusCode').should('eq', 200)
    cy.get('.modal-card').should('not.exist')
    cy.contains('.leaf', `${name} renamed`).should('exist')
  })

  it('edits the column position and reorders the columns by dragging the header', () => {
    const name = unique('Review column')
    cy.intercept('POST', '**/api/workspaces/*/boards/*/columns').as('createColumn')
    cy.intercept('PATCH', '**/api/workspaces/*/columns/*').as('updateColumn')

    cy.visitApp(`/#/boards/${kanban.id}/columns`)
    cy.contains('button', 'New column').click()
    cy.get('.modal-card').contains('.field', 'Name').find('input').type(name)
    cy.get('.modal-card').contains('.field', 'Position').find('input').clear().type('5')
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@createColumn').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.position, JSON.stringify(call.request.body)).to.eq(5)
      created.columns.push(call.response.body.column.id)
    })
    cy.get('.modal-card').should('not.exist')
    cy.contains('.table-row', name).should('contain', '5')

    cy.visitApp(`/#/boards/${kanban.id}`)
    cy.get('.column-header').should('have.length.at.least', 4)
    cy.get('.column-header').then(($headers) => {
      cy.wrap([...$headers].map((header) => header.textContent.trim())).as('beforeOrder')
    })
    cy.window().then((win) => {
      const headers = win.document.querySelectorAll('.column-header')
      const first = headers[0]
      const last = headers[headers.length - 1]
      const dataTransfer = new win.DataTransfer()
      first.dispatchEvent(new win.DragEvent('dragstart', { bubbles: true, dataTransfer }))
      last.dispatchEvent(new win.DragEvent('dragover', { bubbles: true, cancelable: true, dataTransfer }))
      last.dispatchEvent(new win.DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer }))
      first.dispatchEvent(new win.DragEvent('dragend', { bubbles: true, dataTransfer }))
    })
    cy.wait(800)
    cy.get('@updateColumn.all').then((calls) => {
      expect(calls.length, 'the drag must persist the new order').to.be.greaterThan(0)
      calls.forEach((call) => expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(200))
    })
    cy.get('.column-header').then(($after) => {
      const after = [...$after].map((header) => header.textContent.trim())
      cy.get('@beforeOrder').then((before) => {
        expect(after, `order ${JSON.stringify(before)} -> ${JSON.stringify(after)}`).to.not.deep.equal(before)
      })
    })
  })

  it('creates an issue type with a custom select field, edits and removes it', () => {
    const name = unique('Review type')
    cy.intercept('POST', '**/api/workspaces/*/issue-types/*/fields').as('createField')
    cy.intercept('PATCH', '**/api/workspaces/*/fields/*').as('updateField')
    cy.intercept('DELETE', '**/api/workspaces/*/fields/*').as('deleteField')
    cy.intercept('DELETE', '**/api/workspaces/*/issue-types/*').as('deleteType')

    cy.visitIssueTypes()
    cy.contains('button', 'New type').click()
    cy.get('.modal-card').contains('.field', 'Name').find('input').type(name)
    cy.contains('button', 'Add field').click()
    cy.get('.modal-card .field-row input[placeholder="Label"]').type('Priority')
    cy.get('.modal-card .field-row select').select('single_select')
    cy.get('.modal-card .field-row input[placeholder="a, b, c"]').type('low, high')
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@createField').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.type).to.eq('single_select')
      expect(call.request.body.options).to.deep.eq(['low', 'high'])
    })
    cy.get('.modal-card').should('not.exist')
    cy.contains('.table-row', name).should('contain', '1')

    cy.contains('.table-row', name).find('button[title="Edit"]').click()
    cy.get('.modal-card .field-row input[placeholder="a, b, c"]').should('have.value', 'low, high')
    cy.get('.modal-card .field-row input[placeholder="Label"]').clear().type('Severity')
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@updateField').its('response.statusCode').should('eq', 200)
    cy.get('.modal-card').should('not.exist')

    cy.contains('.table-row', name).find('button[title="Edit"]').click()
    cy.get('.modal-card .field-row button[aria-label="Remove"]').click()
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@deleteField').its('response.statusCode').should('eq', 200)
    cy.contains('.table-row', name).should('contain', '0')

    cy.contains('.table-row', name).find('button[title="Delete"]').click()
    cy.get('.dialog-confirm').click()
    cy.wait('@deleteType').its('response.statusCode').should('eq', 200)
    cy.contains('.table-row', name).should('not.exist')
  })

  it('edits and deletes a status and a transition through the real routes', () => {
    const statusName = unique('Review status')
    cy.intercept('POST', '**/api/workspaces/*/workflows/*/statuses').as('createStatus')
    cy.intercept('PATCH', '**/api/workspaces/*/statuses/*').as('updateStatus')
    cy.intercept('DELETE', '**/api/workspaces/*/statuses/*').as('deleteStatus')
    cy.intercept('POST', '**/api/workspaces/*/workflows/*/transitions').as('createTransition')
    cy.intercept('PATCH', '**/api/workspaces/*/transitions/*').as('updateTransition')
    cy.intercept('DELETE', '**/api/workspaces/*/transitions/*').as('deleteTransition')

    cy.wsSnapshot().then((snapshot) => {
      const workflow = snapshot.workflows[0]
      const task = snapshot.issueTypes.find((type) => type.workflowId === workflow.id)
      const from = snapshot.statuses.find((status) => status.workflowId === workflow.id && status.name === 'In Test')
      const to = snapshot.statuses.find((status) => status.workflowId === workflow.id && status.name === 'In Development')

      cy.visitApp(`/#/workflows/${workflow.id}/statuses`)
      cy.contains('button', 'New status').click()
      cy.get('.modal-card').contains('.field', 'Name').find('input').type(statusName)
      cy.get('.modal-card').contains('.field', 'Issue type').find('select').select(task.id)
      cy.get('.modal-card .button:not(.ghost)').click()
      cy.wait('@createStatus').then((call) => {
        expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
        created.statuses.push(call.response.body.status.id)
      })
      cy.get('.modal-card').should('not.exist')
      cy.contains('.table-row', statusName).find('button[title="Edit"]').click()
      cy.get('.modal-card').contains('.field', 'Name').find('input').clear().type(`${statusName} v2`)
      cy.get('.modal-card .button:not(.ghost)').click()
      cy.wait('@updateStatus').its('response.statusCode').should('eq', 200)
      cy.contains('.table-row', `${statusName} v2`).find('button[title="Delete"]').click()
      cy.get('.dialog-confirm').click()
      cy.wait('@deleteStatus').its('response.statusCode').should('eq', 200)
      cy.contains('.table-row', `${statusName} v2`).should('not.exist')

      cy.visitApp(`/#/workflows/${workflow.id}/transitions`)
      cy.contains('button', 'New transition').click()
      field('From').find('select').select(from.id)
      field('To').find('select').select(to.id)
      cy.get('.modal-card .button:not(.ghost)').click()
      cy.wait('@createTransition').then((call) => {
        expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
        created.transitions.push(call.response.body.transition.id)
      })
      cy.get('.modal-card').should('not.exist')
      const transitionRow = () =>
        cy
          .get('.table-row')
          .filter((_, row) => row.textContent.includes('In Test') && row.textContent.includes('In Development'))
      transitionRow().find('button[title="Edit"]').click()
      field('Allowed for').find('select').select('user')
      cy.get('.modal-card .button:not(.ghost)').click()
      cy.wait('@updateTransition').its('response.statusCode').should('eq', 200)
      transitionRow().find('button[title="Delete"]').click()
      cy.get('.dialog-confirm').click()
      cy.wait('@deleteTransition').its('response.statusCode').should('eq', 200)
    })
  })

  it('adds a comment to the issue history', () => {
    const body = unique('Review comment')
    cy.visitApp('/')
    cy.get('.card').contains('.card-id', 'KANB-2').click()
    cy.get('.issue-panel').should('be.visible')
    cy.wsRequest('GET', '/issues/KANB-2').then((detail) => {
      const before = detail.body.history.length
      cy.get('#issue-tab-comments').click()
      cy.get('.issue-panel textarea[aria-label="Comment"]').type(body)
      cy.get('.comments-body > button.button').click()
      cy.get('.issue-panel .comment', { timeout: 10000 }).should('have.length.at.least', 1)
      cy.get('#issue-tab-history').click()
      cy.get('#issue-panel-history [data-history-type="comment"] .history-text')
        .first()
        .should('contain.text', `Commented: ${body}`)
      cy.wsRequest('GET', '/issues/KANB-2').then((after) => {
        expect(after.body.history.length, JSON.stringify(after.body.history)).to.eq(before + 1)
      })
    })
  })

  it('renames the workspace through the real endpoint', () => {
    const name = unique('Review workspace')
    cy.intercept('PATCH', '**/api/workspaces/*').as('updateWorkspace')

    cy.visitApp('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button[title="Edit workspace"]').click({ force: true })
    field('Name').find('input').clear().type(name)
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@updateWorkspace').then((call) => {
      expect(call.response.statusCode, `${call.request.url} ${JSON.stringify(call.response.body)}`).to.eq(200)
    })
    cy.contains('#app-sidebar', name).should('exist')
  })

  it('creates, edits and deletes a repository under Sources', () => {
    const name = unique('Review repo')
    cy.intercept('POST', '**/api/workspaces/*/repositories').as('createRepository')
    cy.intercept('PATCH', '**/api/workspaces/*/repositories/*').as('updateRepository')
    cy.intercept('DELETE', '**/api/workspaces/*/repositories/*').as('deleteRepository')

    cy.visitApp('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button[title="New source"]').click({ force: true })
    field('Name').find('input').type(name)
    field('HTTPS URL').find('input').type('https://github.com/example/review-repo')
    field('Base branch').find('input').clear().type('main')
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@createRepository').then((call) => {
      expect(call.response.statusCode, `${JSON.stringify(call.request.body)} ${JSON.stringify(call.response.body)}`).to.eq(201)
      created.repositories.push(call.response.body.repository.id)
    })
    cy.get('.modal-card').should('not.exist')
    cy.contains('#app-sidebar', name).should('exist')
    cy.contains('#app-sidebar', 'https://github.com/example/review-repo').should('exist')

    cy.contains('.nav-entry', name).find('button[title="Edit source"]').click({ force: true })
    field('Name').find('input').clear().type(`${name} renamed`)
    cy.get('.modal-card .button:not(.ghost)').click()
    cy.wait('@updateRepository').then((call) => {
      expect(call.response.statusCode, `${JSON.stringify(call.request.body)} ${JSON.stringify(call.response.body)}`).to.eq(200)
    })
    cy.contains('#app-sidebar', `${name} renamed`).should('exist')

    cy.contains('.nav-entry', `${name} renamed`).find('button[title="Delete source"]').click({ force: true })
    cy.get('.dialog-confirm').click()
    cy.wait('@deleteRepository').its('response.statusCode').should('eq', 200)
    cy.contains('#app-sidebar', `${name} renamed`).should('not.exist')
  })

  it('creates an issue with a required custom field and edits it in the panel', () => {
    const typeName = unique('Review field type')
    const summary = unique('Review field issue')
    cy.intercept('POST', '**/api/workspaces/*/issues').as('createIssue')
    cy.intercept('PATCH', '**/api/workspaces/*/issues/*').as('updateIssue')

    cy.wsRequest('POST', `/workflows/${workflowId}/issue-types`, { name: typeName, level: 1 }).then((type) => {
      const typeId = type.body.issueType.id
      created.issueTypes.push(typeId)
      cy.wsRequest('POST', `/issue-types/${typeId}/fields`, {
        label: 'Priority',
        key: 'priority',
        type: 'single_select',
        options: ['low', 'high'],
        required: true,
      }).then((fieldResponse) => {
        created.fields.push(fieldResponse.body.field.id)
      })
      cy.wsRequest('POST', `/workflows/${workflowId}/statuses`, {
        name: `${typeName} todo`,
        type: 'initial',
        issueTypeId: typeId,
      }).then((statusResponse) => {
        const statusId = statusResponse.body.status.id
        created.statuses.push(statusId)
        cy.wsRequest('PATCH', `/columns/${todoColumn.id}`, {
          statusIds: [...todoColumn.statusIds, statusId],
        })
      })
    })

    cy.visitApp('/')
    cy.contains('button', 'New task').click()
    cy.get('#create-type').select(typeName)
    cy.get('#create-summary').type(summary)
    cy.get('.new-task-modal .field-value')
      .filter((_, element) => element.querySelector('span')?.textContent.includes('Priority'))
      .find('select')
      .select('low')
    cy.get('.modal-footer').contains('button', 'Create').click()
    cy.wait('@createIssue').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.fields, JSON.stringify(call.request.body)).to.deep.eq({ priority: 'low' })
    })
    cy.get('.new-task-modal').should('not.exist')
    cy.contains('.card', summary).click()
    cy.get('.issue-panel .panel-fields').should('exist')
    cy.get('.issue-panel .panel-fields select').select('high')
    cy.get('.issue-panel .panel-fields').contains('button', 'Save fields').click()
    cy.wait('@updateIssue').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(200)
      expect(call.request.body.fields, JSON.stringify(call.request.body)).to.deep.eq({ priority: 'high' })
    })
  })
})
