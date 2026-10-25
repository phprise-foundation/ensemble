const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const repeat = (character, size) => character.repeat(size)
const description42 = repeat('A', 4096)
const description41 = repeat('A', 4095)
const comment1024 = repeat('C', 1024)
const comment1023 = repeat('B', 1023)
const warningBelow = repeat('W', 3685)
const warningAt = repeat('W', 3686)

let errors

const expectNoErrors = () => {
  cy.then(() => {
    expect(errors, `runtime errors:\n${errors.join('\n')}`).to.deep.equal([])
  })
}

describe('description and comment limits', () => {
  beforeEach(() => {
    cy.viewport(1280, 900)
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.visit('/')
    cy.get('.card', { timeout: 10000 }).should('have.length.at.least', 1)
  })

  it('no aviso em 3685, aviso em 3686', () => {
    cy.contains('button', 'New task').click()
    cy.get('#create-description-limit .field-limit-count').should('have.text', '0/4095')
    cy.get('#create-description-limit .field-limit-warning').should('not.exist')
    cy.get('#create-description')
      .invoke('val', warningBelow)
      .trigger('input')
    cy.get('#create-description-limit .field-limit-count').should('have.text', '3685/4095')
    cy.get('#create-description-limit .field-limit-warning').should('not.exist')
    cy.get('#create-description')
      .invoke('val', warningAt)
      .trigger('input')
    cy.get('#create-description-limit .field-limit-warning').should('be.visible')
    cy.get('#create-description-limit .field-limit-error').should('have.text', '')
    cy.contains('button', 'Create').should('not.be.disabled')
    expectNoErrors()
  })

  it('4095 habilita o Create e grava o texto integral; 4096 bloqueia', () => {
    const summary = 'E2E description 4095'
    cy.intercept('POST', '**/api/workspaces/*/issues').as('createIssue')
    cy.contains('button', 'New task').click()
    cy.get('#create-summary').type(summary)
    cy.get('#create-description')
      .invoke('val', description41)
      .trigger('input')
    cy.get('#create-description-limit .field-limit-count').should('have.text', '4095/4095')
    cy.contains('button', 'Create').should('not.be.disabled')
    cy.get('#create-description')
      .invoke('val', description42)
      .trigger('input')
    cy.get('#create-description-limit .field-limit-error').should('contain', 'excede o limite de 4095')
    cy.contains('button', 'Create').should('be.disabled')
    cy.get('#create-description').should('have.attr', 'aria-invalid', 'true')
    cy.get('#create-description')
      .invoke('val', description41)
      .trigger('input')
    cy.contains('button', 'Create').click()
    cy.wait('@createIssue')
    cy.get('.modal').should('not.exist')
    cy.wsRequest('GET', '/issues').then((response) => {
      const created = response.body.issues.find((issue) => issue.summary === summary)
      expect(created.description).to.equal(description41)
      cy.wrap(created.key).as('createdKey')
    })
    cy.get('@createdKey').then((key) => {
      cy.contains('.card', key).click()
    })
    cy.get('.issue-panel .body').invoke('text').then((text) => {
      expect(text).to.equal(description41)
    })
    expectNoErrors()
  })

  it('aria-describedby liga o campo ao contador na criação', () => {
    cy.contains('button', 'New task').click()
    cy.get('#create-description').should('have.attr', 'aria-describedby', 'create-description-limit')
    cy.get('#create-description-limit').should('exist')
    expectNoErrors()
  })

  it('edição: Edit abre, 4096 bloqueia o Save, Cancel descarta', () => {
    cy.wsRequest('GET', '/issues').then((response) => {
      const target = response.body.issues.find((issue) => issue.key === 'KANB-1')
      cy.wrap(target).as('editTarget')
    })
    cy.get('@editTarget').then((target) => {
      cy.contains('.card', target.key).click()
    })
    cy.get('.issue-panel .body').invoke('text').then((original) => {
      cy.get('.issue-panel').contains('button', 'Edit').click()
      cy.get('.issue-panel textarea[aria-label="Description"]').should(
        'have.attr',
        'aria-describedby',
        'issue-description-limit',
      )
      cy.get('.issue-panel').contains('button', 'Save').should('not.be.disabled')
      cy.get('.issue-panel textarea[aria-label="Description"]')
        .invoke('val', description42)
        .trigger('input')
      cy.get('#issue-description-limit .field-limit-error').should('contain', 'excede o limite de 4095')
      cy.get('.issue-panel').contains('button', 'Save').should('be.disabled')
      cy.get('.issue-panel textarea[aria-label="Description"]').should('have.attr', 'aria-invalid', 'true')
      cy.get('.issue-panel').contains('button', 'Cancel').click()
      cy.get('.issue-panel .body').invoke('text').then((restored) => {
        expect(restored).to.equal(original)
      })
    })
    expectNoErrors()
  })

  it('edição: Save grava o texto novo', () => {
    cy.wsRequest('GET', '/issues/KANB-2').then((response) => {
      const baseline = response.body.description
      cy.contains('.card', 'KANB-2').click()
      cy.get('.issue-panel').contains('button', 'Edit').click()
      cy.get('.issue-panel textarea[aria-label="Description"]')
        .invoke('val', `${baseline}
E2E-SAVED`)
        .trigger('input')
      cy.get('.issue-panel').contains('button', 'Save').click()
      cy.get('.issue-panel .body').invoke('text').then((text) => {
        expect(text.trimEnd()).to.equal(`${baseline}
E2E-SAVED`)
      })
      cy.get('.issue-panel').contains('button', 'Edit').click()
      cy.get('.issue-panel textarea[aria-label="Description"]')
        .invoke('val', baseline)
        .trigger('input')
      cy.get('.issue-panel').contains('button', 'Save').click()
      cy.get('.issue-panel .body').invoke('text').then((text) => {
        expect(text.trimEnd()).to.equal(baseline.trimEnd())
      })
      expectNoErrors()
    })
  })

  it('comentário: 1024 bloqueia com erro e 1023 é enviado', () => {
    cy.contains('.card', 'KANB-1').click()
    cy.get('.issue-panel').invoke('text').then(() => {
      cy.wsRequest('GET', '/issues/KANB-1').then((before) => {
        const commentCount = before.body.comments.length
        cy.get('.issue-panel textarea[aria-label="Comment"]')
          .invoke('val', comment1024)
          .trigger('input')
        cy.get('#issue-comment-limit .field-limit-count').invoke('text').then((text) => {
          expect(parseInt(text, 10)).to.be.greaterThan(1023)
        })
        cy.get('#issue-comment-limit .field-limit-error').should('contain', 'excede o limite de 1023')
        cy.get('.comments-body > button.button').should('have.text', 'Comment').and('be.disabled')
        cy.get('.issue-panel textarea[aria-label="Comment"]').should('have.attr', 'aria-invalid', 'true')
        cy.get('.issue-panel textarea[aria-label="Comment"]')
          .invoke('val', comment1023)
          .trigger('input')
        cy.get('#issue-comment-limit .field-limit-count').should('have.text', '1023/1023')
        cy.get('#issue-comment-limit .field-limit-error').should('have.text', '')
        cy.get('.comments-body > button.button').should('have.text', 'Comment').and('not.be.disabled').click()
        cy.get('.issue-panel .comment', { timeout: 10000 }).should('have.length', commentCount + 1)
        cy.get('.issue-panel .comment p').last().should('have.text', comment1023)
        cy.wsRequest('GET', '/issues/KANB-1').then((after) => {
          expect(after.body.comments.length).to.equal(commentCount + 1)
        })
      })
    })
    expectNoErrors()
  })

  it('comentário aria-describedby aponta para o contador', () => {
    cy.contains('.card', 'KANB-1').click()
    cy.get('.issue-panel textarea[aria-label="Comment"]').should(
      'have.attr',
      'aria-describedby',
      'issue-comment-limit',
    )
    cy.get('#issue-comment-limit').should('exist')
    expectNoErrors()
  })

  it('legado KANB-7: PATCH acima do limite devolve 422 e preserva o original', () => {
    cy.contains('.card', 'KANB-7').click()
    cy.get('.issue-panel .body').invoke('text').then((text) => {
      expect(text).to.contain('LEGACY-DESCRIPTION-START')
      expect(text).to.contain('LEGACY-DESCRIPTION-END')
    })
    cy.get('.issue-panel .comment p').invoke('text').then((text) => {
      expect(text).to.contain('LEGACY-COMMENT-START')
      expect(text).to.contain('LEGACY-COMMENT-END')
    })
    cy.get('.issue-panel').contains('button', 'Edit').click()
    cy.get('.issue-panel textarea[aria-label="Description"]')
      .invoke('val', `${'D'.repeat(4200)}`)
      .trigger('input')
    cy.get('#issue-description-limit .field-limit-error').should('contain', 'excede o limite de 4095')
    cy.get('.issue-panel').contains('button', 'Save').should('be.disabled')
    cy.get('.issue-panel textarea[aria-label="Description"]').should('have.attr', 'aria-invalid', 'true')
    cy.get('.issue-panel').contains('button', 'Cancel').click()
    cy.get('#issue-comment-limit .field-limit-count').should('have.text', '0/1023')
    cy.get('.issue-panel textarea[aria-label="Comment"]')
      .invoke('val', comment1024)
      .trigger('input')
    cy.get('#issue-comment-limit .field-limit-count').invoke('text').then((text) => {
      expect(parseInt(text, 10)).to.be.greaterThan(1023)
    })
    cy.get('#issue-comment-limit .field-limit-error').should('contain', 'excede o limite de 1023')
    cy.get('.comments-body > button.button').should('have.text', 'Comment').and('be.disabled')
    cy.get('.issue-panel textarea[aria-label="Comment"]')
      .invoke('val', comment1023)
      .trigger('input')
    cy.get('#issue-comment-limit .field-limit-error').should('have.text', '')
    cy.get('.comments-body > button.button').should('have.text', 'Comment').and('not.be.disabled')
    cy.get('.issue-panel textarea[aria-label="Comment"]').clear()
    expectNoErrors()
  })

  it('legado KANB-7: reduzir habilita o Save e trocar assignee funciona', () => {
    cy.wsRequest('GET', '/issues/KANB-7').then((before) => {
      const original = before.body.description
      const originalAssignee = before.body.assigneeId
      cy.wsSnapshot().then((snapshot) => {
      const byName = Object.fromEntries(snapshot.agents.map((agent) => [agent.name, agent.id]))
      const targetAssignee = originalAssignee === byName.Vivy ? byName.Mai : byName.Vivy
      const otherAssignee = targetAssignee
      cy.contains('.card', 'KANB-7').click()
      cy.get('.issue-panel').contains('button', 'Edit').click()
      cy.get('.issue-panel textarea[aria-label="Description"]')
        .invoke('val', repeat('R', 200))
        .trigger('input')
      cy.get('#issue-description-limit .field-limit-error').should('have.text', '')
      cy.get('.issue-panel').contains('button', 'Save').should('not.be.disabled')
      cy.get('.issue-panel').contains('button', 'Cancel').click()
      cy.get('.issue-panel .body').invoke('text').then((text) => {
        expect(text).to.contain('LEGACY-DESCRIPTION-END')
      })
      cy.intercept('PATCH', '**/api/workspaces/*/issues/KANB-7').as('patchAssignee')
      cy.get('.issue-panel select').eq(1).select(targetAssignee)
      cy.wait('@patchAssignee')
      cy.get('#issue-comment-limit').should('exist')
      cy.wsRequest('GET', '/issues/KANB-7').then((moved) => {
        expect(moved.body.assigneeId).to.equal(otherAssignee)
      })
      cy.get('.issue-panel select').eq(1).select(originalAssignee)
      cy.wait('@patchAssignee')
      cy.wsRequest('GET', '/issues/KANB-7').then((after) => {
        expect(after.body.assigneeId).to.equal(originalAssignee)
        expect(after.body.description).to.equal(original)
      })
      })
    expectNoErrors()
    })
  })

  it('HTTP: POST com 4096 devolve 422 field=description e não cria issue', () => {
    cy.wsRequest('GET', '/issues').then((before) => {
      const count = before.body.issues.length
      cy.wsSnapshot().then((snapshot) => {
      cy.wsRequest('POST', '/issues', {
        issueTypeId: snapshot.issueTypes[0].id,
        summary: 'E2E over limit',
        description: description42,
      }, { failOnStatusCode: false }).then((response) => {
        expect(response.status).to.equal(422)
        expect(response.body.error).to.be.a('string').and.not.equal('')
        expect(response.body.field).to.equal('description')
        cy.wsRequest('GET', '/issues').then((after) => {
          expect(after.body.issues.length).to.equal(count)
        })
      })
      })
    })
    expectNoErrors()
  })

  it('HTTP: PATCH com 4096 e assigneeId devolve 422 e não altera nada', () => {
    cy.wsRequest('GET', '/issues/KANB-1').then((before) => {
      const baseline = {
        description: before.body.description,
        assigneeId: before.body.assigneeId,
        updatedAt: before.body.updatedAt,
      }
      cy.wsSnapshot().then((agentsSnapshot) => {
      cy.wsRequest('PATCH', '/issues/KANB-1', {
        description: description42,
        assigneeId: agentsSnapshot.agents[0].id,
      }, { failOnStatusCode: false }).then((response) => {
        expect(response.status).to.equal(422)
        expect(response.body.error).to.be.a('string').and.not.equal('')
        expect(response.body.field).to.equal('description')
        cy.wsRequest('GET', '/issues/KANB-1').then((after) => {
          expect(after.body.description).to.equal(baseline.description)
          expect(after.body.assigneeId).to.equal(baseline.assigneeId)
          expect(after.body.updatedAt).to.equal(baseline.updatedAt)
        })
      })
      })
    })
    expectNoErrors()
  })

  it('HTTP: comentário com 1024 devolve 422 field=comment e não grava', () => {
    cy.wsRequest('GET', '/issues/KANB-1/comments').then((before) => {
      const comments = before.body.comments
      cy.wsRequest('POST', '/issues/KANB-1/comments', { body: comment1024 }, { failOnStatusCode: false }).then((response) => {
        expect(response.status).to.equal(422)
        expect(response.body.error).to.be.a('string').and.not.equal('')
        expect(response.body.field).to.equal('body')
        cy.wsRequest('GET', '/issues/KANB-1/comments').then((after) => {
          expect(after.body.comments).to.deep.equal(comments)
        })
      })
    })
    expectNoErrors()
  })
})
