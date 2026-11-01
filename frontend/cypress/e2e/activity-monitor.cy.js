const activityIssue = (issue) => ({
  key: issue.key,
  statusId: issue.statusId,
  assigneeId: issue.assigneeId ?? null,
  blocked: Boolean(issue.blocked),
  readyToMerge: Boolean(issue.readyToMerge),
  position: issue.position,
  enteredAt: issue.enteredAt,
  updatedAt: issue.updatedAt,
})

describe('live board activity and agent monitor', () => {
  beforeEach(() => {
    cy.viewport(1400, 900)
  })

  it('moves a card when the activity feed reports a new status', () => {
    cy.wsSnapshot().then((snapshot) => {
      const issue = snapshot.issues[0]
      const target = snapshot.statuses.find(
        (status) => status.issueTypeId === issue.issueTypeId && status.id !== issue.statusId,
      )
      expect(target, 'a second status for the issue type').to.exist

      cy.intercept('GET', '**/activity', {
        statusCode: 200,
        body: {
          issues: [{ ...activityIssue(issue), statusId: target.id }],
          agents: [],
          runs: [],
          serverTime: new Date().toISOString(),
        },
      }).as('activity')

      cy.visitApp('/')
      cy.wait('@activity')

      cy.contains('.column', target.name).within(() => {
        cy.contains('.card', issue.summary).should('exist')
      })
    })
  })

  it('shows running, sleeping and inactive agents in the console monitor', () => {
    cy.wsSnapshot().then((snapshot) => {
      expect(snapshot.agents.length, 'seeded agents').to.be.at.least(3)
      const issue = snapshot.issues[0]
      const runningAgent = snapshot.agents[0]
      const inactiveAgent = snapshot.agents[2]

      cy.intercept('GET', '**/activity', {
        statusCode: 200,
        body: {
          issues: [activityIssue(issue)],
          agents: snapshot.agents.map((agent) => ({
            id: agent.id,
            name: agent.name,
            active: agent.id !== inactiveAgent.id,
            roleIds: agent.roleIds ?? [],
            language: agent.language ?? 'pt-BR',
          })),
          runs: [
            {
              id: 'run-live-1',
              agentId: runningAgent.id,
              issueId: issue.id,
              issueKey: issue.key,
              issueSummary: issue.summary,
              statusId: issue.statusId,
              state: 'running',
              startedAt: new Date().toISOString(),
              createdAt: new Date().toISOString(),
            },
          ],
          serverTime: new Date().toISOString(),
        },
      }).as('activity')

      cy.visitApp('/')
      cy.wait('@activity')

      cy.contains('.card', issue.summary).find('.card-running').should('contain', runningAgent.name)

      cy.get('.console-toggle').click()
      cy.contains('.console-tab', 'Agents').click()

      cy.get('[data-agent-state="running"]').should('contain', issue.key).and('contain', runningAgent.name)
      cy.get('[data-agent-state="sleeping"]').should('have.length.at.least', 1)
      cy.get('[data-agent-state="inactive"]').should('contain', inactiveAgent.name)

      cy.contains('.console-tab', 'Console').click()
      cy.get('.console-output[role="log"]').should('be.visible')
    })
  })
})
