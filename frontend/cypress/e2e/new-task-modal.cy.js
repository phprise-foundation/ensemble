const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const initialStatusesFor = (workspace, boardName) => {
  const board = workspace.boards.find((candidate) => candidate.name === boardName)
  return workspace.statuses.filter(
    (status) => status.workflowId === board.workflowId && status.type === 'initial',
  )
}

const issueCount = () => cy.wsRequest('GET', '/issues').its('body.issues.length')

const waitForBoard = () => cy.get('.column', { timeout: 10000 }).should('have.length.at.least', 1)

const openModal = () => {
  cy.visitApp('/')
  waitForBoard()
  cy.contains('button', 'New task').click()
  cy.get('.new-task-modal').should('be.visible')
}

describe('new task modal (KAN-123)', () => {
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

  it('opens with focus on summary and lists only the initial statuses of the workflow', () => {
    cy.visitApp('/')
    waitForBoard()
    cy.wsSnapshot().then((workspace) => {
      const initial = initialStatusesFor(workspace, 'Kanban')
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
    cy.wsSnapshot().then((snapshot) => {
      const mai = snapshot.agents.find((agent) => agent.name === 'Mai')
      openModal()
      cy.get('#create-summary').type('E2E modal created task')
      cy.get('#create-description').type('created by cypress')
      cy.get('#create-assignee').select(mai.id)
      cy.get('.modal-footer').contains('button', 'Create').click()
      cy.get('.new-task-modal').should('not.exist')
      cy.wsRequest('GET', '/issues').then((issues) => {
        const created = issues.body.issues.find((issue) => issue.summary === 'E2E modal created task')
        expect(created).to.exist
        expect(created.assigneeId).to.equal(mai.id)
        cy.get('.column')
          .contains('.column-title', 'Todo')
          .parents('.column')
          .contains('.card', created.key)
          .should('exist')
      })
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
    cy.intercept('POST', '**/api/workspaces/*/issues', {
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


  it('editing an existing issue keeps working', () => {
    cy.wsRequest('GET', '/issues').then((issues) => {
      const target = issues.body.issues.find((issue) => issue.key === 'KANB-1')
      cy.wsSnapshot().then((snapshot) => {
      const mai = snapshot.agents.find((agent) => agent.name === 'Mai')
      cy.wsRequest('PATCH', `/issues/KANB-1`, {
        summary: 'summary updated by kan-123',
        assigneeId: mai.id,
      }).then((response) => {
        expect(response.status).to.eq(200)
        expect(response.body.issue.summary).to.eq('summary updated by kan-123')
        expect(response.body.issue.assigneeId).to.eq(mai.id)
        cy.wsRequest('PATCH', `/issues/KANB-1`, {
          summary: target.summary,
          assigneeId: target.assigneeId,
        }).its('status').should('eq', 200)
      })
      })
    })
    cy.then(() => {
      expect(errors).to.deep.equal([])
    })
  })
})
