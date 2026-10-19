const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const initialStatusesFor = (workspace, boardId) => {
  const board = workspace.boards.find((candidate) => candidate.id === boardId)
  return workspace.statuses.filter(
    (status) => status.workflowId === board.workflowId && status.type === 'initial',
  )
}

const issueCount = () =>
  cy.request('GET', '/api/issues').its('body.length')

const openModal = () => {
  cy.visit('/?sim=0')
  cy.contains('button', 'New task').click()
  cy.get('.new-task-modal').should('be.visible')
}

describe('new task modal (KAN-123)', () => {
  let errors
  const createdIssues = []
  const extraStatusIds = []

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
    for (const id of extraStatusIds) {
      cy.request('DELETE', `/api/statuses/${id}`)
    }
    extraStatusIds.length = 0
    for (const id of createdIssues) {
      cy.request('DELETE', `/api/issues/${id}`)
    }
    createdIssues.length = 0
    cy.request('PATCH', '/api/statuses/to-develop', { type: 'initial' })
      .its('body.type')
      .should('eq', 'initial')
  })

  it('opens with focus on summary and lists only the initial statuses of the workflow', () => {
    cy.visit('/?sim=0')
    cy.request('GET', '/api/workspace').then((workspace) => {
      const initial = initialStatusesFor(workspace.body, 'KANB')
      expect(initial.length).to.be.at.least(1)
      cy.contains('button', 'New task').click()
      cy.get('.new-task-modal').should('be.visible')
      cy.focused().should('have.id', 'create-summary')
      cy.get('#create-status option').then(($options) => {
        const values = [...$options].map((option) => option.value)
        expect(values).to.deep.equal(initial.map((status) => status.id))
      })
      cy.get('#create-status').should('have.value', initial[0].id)
      cy.then(() => {
        expect(errors).to.deep.equal([])
      })
    })
  })

  for (const [width, height] of [[375, 667], [768, 900], [1280, 800]]) {
    it(`inputs fill the modal body width at ${width}x${height} without overflow`, () => {
      cy.viewport(width, height)
      openModal()
      cy.get('.modal-body').then(($body) => {
        const body = $body[0]
        const useful = body.clientWidth - 32
        cy.get('#create-summary').then(($input) => {
          const input = $input[0].getBoundingClientRect().width
          expect(input).to.be.closeTo(useful, 1)
        })
        cy.get('#create-description').then(($area) => {
          const area = $area[0].getBoundingClientRect().width
          expect(area).to.be.closeTo(useful, 1)
        })
      })
      cy.document().then((doc) => {
        expect(doc.documentElement.scrollWidth).to.be.at.most(doc.documentElement.clientWidth)
      })
      cy.get('.modal-card').then(($card) => {
        const card = $card[0].getBoundingClientRect()
        expect(card.left).to.be.at.least(0)
        expect(card.right).to.be.at.most(width)
        expect(card.top).to.be.at.least(0)
        expect(card.bottom).to.be.at.most(height)
      })
      cy.then(() => {
        expect(errors).to.deep.equal([])
      })
    })
  }

  it('creates a task with assignee and the card lands in Todo with the initial status', () => {
    openModal()
    cy.get('#create-summary').type('E2E modal created task')
    cy.get('#create-description').type('created by cypress')
    cy.get('#create-assignee').select('mai')
    cy.get('.modal-footer').contains('button', 'Create').click()
    cy.get('.new-task-modal').should('not.exist')
    cy.request('GET', '/api/issues').then((issues) => {
      const created = issues.body.find((issue) => issue.summary === 'E2E modal created task')
      expect(created).to.exist
      createdIssues.push(created.id)
      expect(created.assigneeId).to.equal('mai')
      expect(created.statusId).to.equal('to-develop')
      cy.get('.column')
        .contains('.column-title', 'Todo')
        .parents('.column')
        .contains('.card', created.id)
        .should('exist')
    })
    cy.then(() => {
      expect(errors).to.deep.equal([])
    })
  })

  it('empty summary is rejected inline with focus back on the field', () => {
    openModal()
    cy.get('#create-summary').type('temporary')
    cy.get('#create-summary').clear()
    cy.get('.modal-footer').contains('button', 'Create').click()
    cy.get('#create-summary-error')
      .should('have.attr', 'role', 'alert')
      .and('be.visible')
    cy.get('#create-summary')
      .should('have.attr', 'aria-invalid')
      .and('not.eq', 'false')
    cy.get('#create-summary').should('have.attr', 'aria-describedby', 'create-summary-error')
    cy.focused().should('have.id', 'create-summary')
    cy.get('.new-task-modal').should('be.visible')
    cy.then(() => {
      expect(errors).to.deep.equal([])
    })
  })

  it('server 422 with field=statusId shows the status error without closing the modal', () => {
    openModal()
    cy.intercept('POST', '/api/issues', {
      statusCode: 422,
      body: {
        error: 'a nova task precisa nascer em um status inicial',
        field: 'statusId',
      },
    }).as('createIssue')
    cy.get('#create-summary').type('intercepted attempt')
    cy.get('.modal-footer').contains('button', 'Create').click()
    cy.wait('@createIssue')
    cy.get('#create-status-error')
      .should('be.visible')
      .and('have.attr', 'role', 'alert')
    cy.get('#create-status-error').then(($error) => {
      expect($error.text()).to.contain('precisa nascer em um status inicial')
    })
    cy.get('#create-status')
      .should('have.attr', 'aria-invalid')
      .and('not.eq', 'false')
    cy.get('#create-status').should('have.attr', 'aria-describedby', 'create-status-error')
    cy.get('.new-task-modal').should('be.visible')
    cy.then(() => {
      expect(errors).to.deep.equal([])
    })
  })

  context('direct HTTP contract', () => {
    let baseline

    beforeEach(() => {
      cy.request('GET', '/api/issues').then((issues) => {
        baseline = issues.body.length
      })
    })

    const expectRejected = (payload, field) => {
      cy.request({
        method: 'POST',
        url: '/api/issues',
        body: payload,
        failOnStatusCode: false,
      }).then((response) => {
        expect(response.status).to.eq(422)
        expect(response.body.field).to.eq(field)
        return issueCount().then((count) => expect(count).to.eq(baseline))
      })
    }

    const expectCreated = (payload) =>
      cy
        .request('POST', '/api/issues', payload)
        .then((response) => {
          expect(response.status).to.eq(201)
          createdIssues.push(response.body.id)
          return cy.wrap(response.body)
        })

    it('final, transitional, unknown and foreign statuses are rejected', () => {
      expectRejected({ boardId: 'KANB', summary: 'final try', statusId: 'done' }, 'statusId')
      expectRejected(
        { boardId: 'KANB', summary: 'transitional try', statusId: 'in-development' },
        'statusId',
      )
      expectRejected(
        { boardId: 'KANB', summary: 'unknown try', statusId: 'nonexistent' },
        'statusId',
      )
    })

    it('a status from another workflow is rejected', () => {
      cy.request('POST', '/api/statuses', {
        id: 'foreign-initial',
        workflowId: 'other-workflow',
        type: 'initial',
        name: 'Foreign',
        color: '#000000',
      }).then(() => {
        extraStatusIds.push('foreign-initial')
        expectRejected(
          { boardId: 'KANB', summary: 'foreign try', statusId: 'foreign-initial' },
          'statusId',
        )
      })
    })

    it('an unknown board is rejected with field=boardId', () => {
      expectRejected({ boardId: 'NOPE', summary: 'board try', statusId: 'to-develop' }, 'boardId')
    })

    it('omitted status defaults to the first initial', () => {
      cy.request('GET', '/api/workspace').then((workspace) => {
        const first = initialStatusesFor(workspace.body, 'KANB')[0]
        expectCreated({ boardId: 'KANB', summary: 'status omitted' }).then((issue) => {
          expect(issue.statusId).to.eq(first.id)
          expect(issue).to.not.have.property('columnId')
        })
      })
    })

    it('a forged columnId is ignored and the column is derived from the status', () => {
      cy.visit('/?sim=0')
      expectCreated({
        boardId: 'KANB',
        summary: 'forged column',
        statusId: 'to-develop',
        columnId: 'done',
      }).then((issue) => {
        expect(issue.statusId).to.eq('to-develop')
        expect(issue).to.not.have.property('columnId')
        cy.get('.column')
          .contains('.column-title', 'Todo')
          .parents('.column')
          .contains('.card', issue.id)
          .should('exist')
        cy.get('.column')
          .contains('.column-title', 'Done')
          .parents('.column')
          .contains('.card', issue.id)
          .should('not.exist')
      })
    })

    it('a workflow without an initial status answers 422 and the UI blocks creation', () => {
      cy.request('PATCH', '/api/statuses/to-develop', { type: 'transitional' })
      expectRejected({ boardId: 'KANB', summary: 'no initial try' }, 'statusId')
      openModal()
      cy.get('#create-no-initial')
        .should('be.visible')
        .and('have.attr', 'role', 'alert')
      cy.get('#create-status').should('be.disabled')
      cy.get('.modal-footer').contains('button', 'Create').should('be.disabled')
      cy.get('#create-summary').should('not.be.disabled')
      cy.then(() => {
        expect(errors).to.deep.equal([])
      })
    })
  })

  it('editing an existing issue keeps working', () => {
    cy.request('GET', '/api/issues').then((issues) => {
      const target = issues.body.find((issue) => issue.id === 'KANB-1')
      cy.request('PATCH', `/api/issues/${target.id}`, {
        summary: 'summary updated by kan-123',
        assigneeId: 'mai',
      }).then((response) => {
        expect(response.status).to.eq(200)
        expect(response.body.summary).to.eq('summary updated by kan-123')
        expect(response.body.assigneeId).to.eq('mai')
        cy.request('PATCH', `/api/issues/${target.id}`, {
          summary: target.summary,
          assigneeId: target.assigneeId,
        }).its('status').should('eq', 200)
      })
    })
    cy.then(() => {
      expect(errors).to.deep.equal([])
    })
  })
})
