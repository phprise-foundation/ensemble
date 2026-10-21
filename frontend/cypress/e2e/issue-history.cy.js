const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const openIssue = (issueId) => {
  cy.get('.card').contains('.card-id', issueId).click()
  cy.get('.issue-panel').should('be.visible')
  cy.get('.issue-panel .panel-title').should('not.be.empty')
}

const historyTypes = () =>
  cy.get('#issue-panel-history .history-entry').then(($entries) =>
    $entries.map((_, element) => element.dataset.historyType).get(),
  )

const historyDatetimes = () =>
  cy.get('#issue-panel-history time').then(($times) =>
    $times.map((_, element) => element.getAttribute('datetime')).get(),
  )

describe('issue history tab', () => {
  let errors

  beforeEach(() => {
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.viewport(1280, 800)
  })

  it('opens KANB-1 with Comments selected, counter only there and History hidden', () => {
    cy.visit('/')
    cy.get('vite-error-overlay').should('not.exist')
    openIssue('KANB-1')
    cy.request('GET', '/api/issues/KANB-1').then((current) => {
      cy.get('#issue-tab-comments')
        .should('have.attr', 'aria-selected', 'true')
        .and('contain.text', `Comments (${current.body.comments.length})`)
    })
    cy.get('#issue-tab-history').should('have.attr', 'aria-selected', 'false')
    cy.get('#issue-panel-comments').should('be.visible')
    cy.get('#issue-panel-history').should('not.be.visible')
    cy.get('#issue-tab-history').click()
    cy.get('#issue-panel-history').should('be.visible')
    cy.get('#issue-panel-history .history-entry').should('have.length', 4)
    cy.get('#issue-panel-comments').should('not.be.visible')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('shows the KANB-1 timeline with icon, actor, datetime and summary per event type', () => {
    cy.visit('/')
    openIssue('KANB-1')
    cy.get('#issue-tab-history').click()
    historyTypes().then((types) => {
      expect(types).to.include.members(['created', 'summary', 'description', 'assignee'])
    })
    cy.get('#issue-panel-history .history-entry').each(($entry) => {
      expect($entry.find('.history-icon svg').length, 'ícone do evento').to.equal(1)
      expect($entry.find('.history-actor').text().trim(), 'autor do evento').to.not.equal('')
      expect($entry.find('time[datetime]').length, 'timestamp do evento').to.equal(1)
      expect($entry.find('.history-text').text().trim(), 'resumo do evento').to.not.equal('')
    })
    cy.get('#issue-panel-history [data-history-type="created"] .history-text').should(
      'contain.text',
      'Issue created',
    )
    cy.get('#issue-panel-history [data-history-type="assignee"] .history-text').should(
      'contain.text',
      'Assignee',
    )
  })

  it('covers the status event on KANB-2 and lists events in reverse chronological order', () => {
    cy.visit('/')
    openIssue('KANB-2')
    cy.get('#issue-tab-history').click()
    historyTypes().then((types) => {
      expect(types).to.include('status')
      expect(types.filter((type) => type === 'status').length).to.be.at.least(1)
    })
    historyDatetimes().then((datetimes) => {
      expect(datetimes.length, 'eventos listados').to.equal(4)
      const parsed = datetimes.map((value) => Date.parse(value))
      parsed.forEach((timestamp, index) => {
        expect(parsed[index], 'datetime válido').to.not.be.NaN
        if (index > 0) {
          expect(parsed[index - 1], 'ordem cronológica inversa').to.be.at.least(timestamp)
        }
      })
    })
    cy.get('#issue-panel-history [data-history-type="status"] .history-text')
      .first()
      .should('contain.text', 'Status')
      .and('contain.text', '→')
    cy.get('#issue-panel-history .comment').should('not.exist')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('shows the empty state on KANB-6 and resets to Comments when another issue opens', () => {
    cy.visit('/')
    openIssue('KANB-6')
    cy.get('#issue-tab-history').click()
    cy.get('#issue-panel-history').should('contain.text', 'No history yet')
    cy.get('#issue-panel-history .history-entry').should('not.exist')
    cy.get('.issue-panel [aria-label="Close"]').click()
    cy.get('.issue-panel').should('not.exist')
    openIssue('KANB-2')
    cy.get('#issue-tab-comments').should('have.attr', 'aria-selected', 'true')
    cy.get('#issue-panel-comments').should('be.visible')
    cy.get('#issue-panel-history').should('not.be.visible')
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('supports arrow, Home, End, Enter and Space with roving tabindex and visible focus', () => {
    cy.visit('/')
    openIssue('KANB-1')
    const tabs = ['#issue-tab-comments', '#issue-tab-history']
    cy.get('#issue-tab-comments').focus()
    cy.get('#issue-tab-comments').type('{rightArrow}')
    cy.focused().should('have.id', 'issue-tab-history')
    cy.focused().should('have.css', 'outline-style').and('not.eq', 'none')
    cy.get('#issue-tab-history').should('have.attr', 'aria-selected', 'true')
    cy.get('#issue-panel-history').should('be.visible')
    cy.get('#issue-tab-history').type('{rightArrow}')
    cy.focused().should('have.id', 'issue-tab-comments')
    cy.get('#issue-tab-comments').type('{leftArrow}')
    cy.focused().should('have.id', 'issue-tab-history')
    cy.get('#issue-tab-history').type('{leftArrow}')
    cy.focused().should('have.id', 'issue-tab-comments')
    cy.get('#issue-tab-comments').type('{end}')
    cy.focused().should('have.id', 'issue-tab-history')
    cy.get('#issue-tab-history').type('{home}')
    cy.focused().should('have.id', 'issue-tab-comments')
    cy.get('#issue-tab-comments').type('{enter}')
    cy.get('#issue-panel-comments').should('be.visible')
    cy.get('#issue-panel-history').should('not.be.visible')
    cy.get('#issue-tab-comments').type('{end}')
    cy.focused().should('have.id', 'issue-tab-history')
    cy.focused().type('{enter}')
    cy.get('#issue-panel-history').should('be.visible')
    cy.get('#issue-tab-history').should('have.attr', 'aria-selected', 'true')
    cy.focused().type(' ')
    cy.get('#issue-panel-history').should('be.visible')
    cy.get('#issue-tab-history').should('have.attr', 'aria-selected', 'true')
    cy.get('#issue-tab-history').then(($history) => {
      expect($history.attr('aria-controls')).to.equal('issue-panel-history')
      expect($history.attr('tabindex')).to.equal('0')
    })
    cy.get('#issue-tab-comments').should('have.attr', 'tabindex', '-1')
    cy.get('.tabs').should('have.attr', 'role', 'tablist')
    cy.get('#issue-panel-history').should(
      'have.attr',
      'aria-labelledby',
      'issue-tab-history',
    )
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })

  it('shows the Prototype badge with ?sim=1 and hides it with ?sim=0 while History keeps working', () => {
    cy.visit('/?sim=1')
    openIssue('KANB-1')
    cy.get('#issue-tab-history .badge.sim').should('contain.text', 'Prototype')
    cy.get('vite-error-overlay').should('not.exist')
    cy.get('#issue-tab-history').click()
    cy.get('#issue-panel-history .history-entry').should('have.length', 4)
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))

    cy.clearLocalStorage('sim.history')
    cy.visit('/?sim=0')
    openIssue('KANB-1')
    cy.get('#issue-tab-history .badge.sim').should('not.exist')
    cy.get('#issue-tab-history').click()
    cy.get('#issue-panel-history .history-entry').should('have.length', 4)
    cy.then(() => expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.deep.equal([]))
  })
})
