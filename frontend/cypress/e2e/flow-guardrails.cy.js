const unique = (prefix) => `${prefix} ${Date.now()}`

const field = (label) =>
  cy.get('.modal-card .field').filter((_, element) => element.querySelector('span')?.textContent.trim() === label)

describe('role capabilities and blocked issues', () => {
  beforeEach(() => {
    cy.viewport(1400, 900)
  })

  it('creates a role with explicit delivery capabilities', () => {
    const name = unique('E2E Capability Role')
    cy.intercept('POST', '**/teams/*/roles').as('createRole')

    cy.visitRoles()
    cy.contains('button', 'New role').click()
    field('Name').find('input').type(name)
    field('Writes code').find('input[type="checkbox"]').check()
    field('Reviews changes').find('input[type="checkbox"]').check()
    field('Tests deliverables').find('input[type="checkbox"]').check()
    field('Merges pull requests').find('input[type="checkbox"]').check()
    field('Deploys').find('input[type="checkbox"]').check()
    cy.contains('.modal-footer button', 'Save').click()

    cy.wait('@createRole').then((call) => {
      expect(call.response.statusCode, JSON.stringify(call.response.body)).to.eq(201)
      expect(call.request.body.implement).to.eq(true)
      expect(call.request.body.review).to.eq(true)
      expect(call.request.body.test).to.eq(true)
      expect(call.request.body.merge).to.eq(true)
      expect(call.request.body.deploy).to.eq(true)
    })
    cy.contains('.table-row', name).should('exist')
    cy.contains('.table-row', name).find('button[aria-label="Delete"]').click()
    cy.get('.dialog-confirm').click()
    cy.contains('.table-row', name).should('not.exist')
  })

  it('shows a ready-to-merge issue and disables new runs', () => {
    cy.wsRequest('PATCH', '/issues/KANB-1', { readyToMerge: true }).its('status').should('eq', 200)

    cy.visitApp('/')
    cy.contains('.card', 'Frame the onboarding intent').click()
    cy.get('.panel-ready').should('contain', 'Ready to merge')
    cy.contains('.panel-runs button', 'Run now').should('be.disabled')

    cy.wsRequest('PATCH', '/issues/KANB-1', { readyToMerge: false })
  })

  it('merges a ready issue from the panel', () => {
    cy.wsRequest('PATCH', '/issues/KANB-1', { readyToMerge: true }).its('status').should('eq', 200)

    cy.wsSnapshot().then((snapshot) => {
      const issue = snapshot.issues.find((candidate) => candidate.key === 'KANB-1')
      const done = snapshot.statuses.find(
        (status) => status.issueTypeId === issue.issueTypeId && status.name === 'Done',
      )
      cy.intercept('POST', '**/issues/KANB-1/merge', {
        statusCode: 200,
        body: {
          issue: { ...issue, statusId: done.id, readyToMerge: false },
          merge: { url: 'https://github.com/example/repo/pull/1', sha: 'abc123', message: 'merged' },
        },
      }).as('merge')

      cy.visitApp('/')
      cy.contains('.card', 'Frame the onboarding intent').click()
      cy.get('.panel-ready').should('exist')
      cy.contains('.panel-ready button', 'Merge PR').click()
      cy.get('.dialog-confirm').click()
      cy.wait('@merge').its('request.body.method').should('eq', 'merge')
      cy.get('.toast').should('contain', 'Pull request merged.')
      cy.get('.panel-ready').should('not.exist')
      cy.get('.issue-panel .field')
        .filter((_, element) => element.querySelector('span')?.textContent.trim() === 'Status')
        .find('select')
        .should('have.value', done.id)

      cy.wsRequest('PATCH', '/issues/KANB-1', { readyToMerge: false })
    })
  })

  it('blocks and unblocks an issue from the panel', () => {
    cy.wsRequest('PATCH', '/issues/KANB-1', { blocked: true }).its('status').should('eq', 200)
    cy.intercept('PATCH', '**/issues/KANB-1').as('updateIssue')

    cy.visitApp('/')
    cy.contains('.card', 'Frame the onboarding intent').click()
    cy.get('.panel-blocked').should('contain', 'Blocked')
    cy.contains('.panel-blocked button', 'Unblock').click()
    cy.wait('@updateIssue').its('request.body.blocked').should('eq', false)
    cy.get('.panel-blocked').should('not.exist')
  })
})
