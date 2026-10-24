// Artefato de QA (Ian) — KAN-139: prova os fluxos E2E do serviço central de
// diálogos e toasts (KAN-127 §8.4) na interface em execução: cancelamento e
// confirmação, foco inicial no Cancelar, retorno de foco ao gatilho,
// role/aria-modal/alertdialog, scrim ignorado no confirm e toast com role=alert.
// O coletor global de cypress/support/e2e.js reprova erro/rejeição/console.error.
describe('diálogos e toasts na interface em execução', () => {
  const overlay = '.dialog-overlay'
  const card = '.dialog-card'

  const abrirDialogDanger = (alias, rotuloDelete = 'Delete') => {
    cy.get(alias).find(`[aria-label="${rotuloDelete}"]`).first().click({ force: true })
    cy.get(card)
      .should('have.attr', 'role', 'alertdialog')
      .and('have.attr', 'aria-modal', 'true')
      .and('have.attr', 'aria-labelledby', 'dialog-title')
    cy.get('#dialog-title').should('contain', 'Delete?')
    cy.focused().should('have.class', 'ghost').and('contain', 'Cancel')
  }

  const abrirDeleteSource = (nome) => {
    cy.get('#app-sidebar').contains('.nav-entry', 'Sources').scrollIntoView().click({ force: true })
    cy.get('#app-sidebar .submenu .nav-entry')
      .filter((_, el) => el.textContent.includes(nome))
      .find('[aria-label="Delete source"]')
      .first()
      .click({ force: true })
  }

  it('confirm: Enter confirma, registro some e toast de sucesso aparece', () => {
    cy.visit('/#/teams/default/roles')
    cy.get('.table-row').filter((_, el) => el.textContent.includes('Coder')).as('linha')
    abrirDialogDanger('@linha')
    cy.get(card).trigger('keydown', { key: 'Enter' })
    cy.get(card).should('not.exist')
    cy.get('.toast')
      .should('have.class', 'success')
      .and('contain', '"Coder" deleted.')
    cy.get('.table-row').contains('Coder').should('not.exist')
  })

  it('confirm: clique no botão Cancelar cancela, registro permanece e o foco volta ao gatilho', () => {
    cy.visit('/#/teams/default/roles')
    cy.get('.table-row').filter((_, el) => el.textContent.includes('Tech Lead')).as('linha')
    abrirDialogDanger('@linha')
    cy.get(card).find('button.ghost').click()
    cy.get(card).should('not.exist')
    cy.get('.table-row').contains('Tech Lead').should('exist')
    cy.focused().should('have.attr', 'aria-label', 'Delete')
  })

  it('confirm: Escape cancela e o scrim não fecha a caixa', () => {
    cy.visit('/#/workflows/standard/issue-types')
    cy.get('.table-row').filter((_, el) => el.textContent.includes('Task')).as('linha')
    abrirDialogDanger('@linha')
    cy.get(overlay).click('topLeft', { force: true })
    cy.get(card).should('exist')
    cy.get(card).trigger('keydown', { key: 'Escape' })
    cy.get(card).should('not.exist')
    cy.get('.table-row').contains('Task').should('exist')
    cy.focused().should('have.attr', 'aria-label', 'Delete')
  })

  it('agents: confirmação pela exclusão do fluxo remove o agente e mostra toast', () => {
    cy.visit('/#/teams/default/agents')
    abrirDialogDanger('.content')
    cy.get(card).find('button').last().click()
    cy.get(card).should('not.exist')
    cy.get('.toast').should('contain', 'deleted.')
    cy.contains('Vivy').should('not.exist')
  })

  it('agents: erro na exclusão anuncia toast com role="alert"', () => {
    cy.intercept('DELETE', '**/api/agents/*', { statusCode: 500, body: { error: 'falha simulada' } }).as('falha')
    cy.visit('/#/teams/default/agents')
    abrirDialogDanger('.content')
    cy.get(card).find('button').last().click()
    cy.get(card).should('not.exist')
    cy.get('.toast')
      .should('have.attr', 'role', 'alert')
      .and('contain', 'falha simulada')
  })

  it('sidebar: exclusão de source cancelada via Cancelar mantém a source', () => {
    cy.request('POST', '/api/sources', { path: '/tmp', name: 'src-e2e', workspaceId: 'default' })
    cy.visit('/')
    abrirDeleteSource('src-e2e')
    cy.get(card).find('button.ghost').click()
    cy.get(card).should('not.exist')
    cy.get('#app-sidebar').contains('src-e2e').should('exist')
  })

  it('sidebar: exclusão de source confirmada via Enter remove e mostra toast', () => {
    cy.wsRequest('POST', '/repositories', { name: 'src-enter', provider: 'github', url: 'https://github.com/example/src-enter', baseBranch: 'main' })
    cy.visit('/')
    abrirDeleteSource('src-enter')
    cy.get(card).trigger('keydown', { key: 'Enter' })
    cy.get(card).should('not.exist')
    cy.get('.toast').should('have.class', 'success').and('contain', '"src-enter" deleted.')
    cy.get('#app-sidebar').contains('src-enter').should('not.exist')
  })

  it('overlay ocupa a tela em viewport estreita (390×844)', () => {
    cy.viewport(390, 844)
    cy.visit('/#/teams/default/roles')
    abrirDialogDanger('.content')
    cy.get(card).then(($card) => {
      const rect = $card[0].getBoundingClientRect()
      expect(rect.width).to.be.lessThan(390)
      expect(rect.height).to.be.lessThan(844)
    })
    cy.get(card).trigger('keydown', { key: 'Escape' })
    cy.get(card).should('not.exist')
  })
})
