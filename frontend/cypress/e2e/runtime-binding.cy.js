const unique = (prefix) => `${prefix} ${Date.now()}`

const createRepository = (name) =>
  cy
    .wsRequest('POST', '/repositories', {
      name,
      provider: 'github',
      url: `https://github.com/example/${Date.now()}-${name.toLowerCase().replace(/\W+/g, '-')}.git`,
    })
    .then((created) => created.body.repository.id)

describe('issue workdir binding, runs and conflicts', () => {
  beforeEach(() => {
    cy.viewport(1400, 900)
  })

  it('attaches a repository to a scratch issue', () => {
    let repositoryId = null
    cy.intercept('POST', '**/issues/*/repository').as('attach')
    createRepository('Bind target').then((id) => {
      repositoryId = id
      cy.wrap(id).as('repositoryId')
    })

    cy.visitApp('/')
    cy.contains('.card', 'Fix the login redirect loop').click()
    cy.contains('.panel-repository', 'Scratch workdir').should('exist')

    cy.get('@repositoryId').then((id) => {
      cy.get('.panel-repository select').select(id)
    })
    cy.contains('.panel-repository button', 'Attach').click()
    cy.wait('@attach').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(200)
      expect(call.request.body.repositoryId).to.eq(repositoryId)
    })

    cy.contains('.panel-repository .body', 'Bind target').should('exist')
    cy.contains('.panel-repository', 'Workdir: pending').should('exist')
    cy.contains('.panel-repository', 'Scratch workdir').should('not.exist')
  })

  it('creates a task bound to a repository', () => {
    const summary = unique('Bound task')
    createRepository('Task repository').then((id) => {
      cy.wrap(id).as('repositoryId')
    })
    cy.intercept('POST', '**/api/workspaces/*/issues').as('createIssue')

    cy.visitApp('/')
    cy.contains('button', 'New task').click()
    cy.get('#create-summary').type(summary)
    cy.contains('Tasks without a repository run in a scratch workdir').should('exist')
    cy.get('@repositoryId').then((id) => {
      cy.get('#create-repository').select(id)
    })
    cy.contains('Tasks without a repository run in a scratch workdir').should('not.exist')
    cy.contains('.modal-footer button', 'Create').click()
    cy.wait('@createIssue').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.repositoryId).to.be.a('string').and.not.be.empty
    })

    cy.contains('.card', summary).click()
    cy.contains('.panel-repository .body', 'Task repository').should('exist')
    cy.contains('.panel-repository', 'Workdir: pending').should('exist')
  })

  it('lists runs and resolves a workdir conflict', () => {
    createRepository('Conflict repository').then((repositoryId) => {
      cy.wsSnapshot().then((snapshot) => {
        const workspaceId = snapshot.workspaces[0].id
        const kaio = snapshot.agents.find((agent) => agent.name === 'Kaio')
        const runs = [
          {
            id: 'run-succeeded',
            workspaceId,
            agentId: kaio.id,
            issueId: 'KANB-3',
            state: 'succeeded',
            attempt: 1,
            reason: null,
            message: 'applied the change to the board',
            prompt: 'do the work',
            createdAt: '2026-10-29T11:00:00+00:00',
            startedAt: '2026-10-29T11:00:01+00:00',
            finishedAt: '2026-10-29T11:00:30+00:00',
          },
          {
            id: 'run-skipped',
            workspaceId,
            agentId: kaio.id,
            issueId: 'KANB-3',
            state: 'skipped',
            attempt: 1,
            reason: 'the issue workdir is in conflict',
            message: null,
            prompt: null,
            createdAt: '2026-10-29T12:00:00+00:00',
            startedAt: null,
            finishedAt: null,
          },
        ]
        cy.intercept('GET', '**/issues/KANB-3/runs', { runs }).as('runs')
        cy.intercept('GET', '**/snapshot', (request) =>
          request.continue((response) => {
            const issue = response.body.issues.find((candidate) => candidate.key === 'KANB-3')
            issue.repositoryId = repositoryId
            issue.workdirState = 'conflict'
          }),
        ).as('snapshot')
        cy.intercept('POST', '**/issues/KANB-3/workdir/resolve', {
          statusCode: 200,
          body: {
            binding: { repositoryId, branch: 'agent/KANB-3', state: 'bound' },
          },
        }).as('resolve')

        cy.visitApp('/')
        cy.contains('.card', 'Add dark theme to the board').click()
        cy.wait('@runs')
        cy.get('.run-entry').should('have.length', 2)
        cy.get('.run-entry').first().should('have.attr', 'data-run-state', 'skipped')
        cy.contains('.run-entry', 'the issue workdir is in conflict').should('exist')
        cy.contains('.run-entry', 'Kaio').should('exist')
        cy.get('.run-entry[data-run-state="succeeded"] summary').click()
        cy.get('.run-entry[data-run-state="succeeded"] pre').should(
          'contain',
          'applied the change to the board',
        )

        cy.get('.conflict-box').should('exist')
        cy.contains('.conflict-actions button', 'Remote wins').click()
        cy.wait('@resolve').its('request.body.strategy').should('eq', 'remote_wins')
        cy.get('.conflict-box').should('not.exist')
        cy.contains('.panel-repository', 'Workdir: bound').should('exist')
      })
    })
  })
})
