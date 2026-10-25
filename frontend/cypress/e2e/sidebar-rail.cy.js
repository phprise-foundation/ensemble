Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const trackConsole = (errors) => {
  cy.on('window:before:load', (win) => {
    collectErrors(win, errors)
    cy.stub(win.console, 'error', (...args) => {
      errors.push(`console.error: ${args.join(' ')}`)
    })
  })
}

const collapse = () => {
  cy.get('button.sidebar-toggle').click()
  cy.get('#app-sidebar').should('have.class', 'collapsed')
  cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'false')
}

const expectOneCurrent = (title) => {
  cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.length', 1)
  cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.attr', 'title', title)
}

describe('rail do sidebar colapsado', () => {
  let errors
  let boardId
  let workflowId

  before(() => {
    cy.wsSnapshot().then((snapshot) => {
      boardId = snapshot.boards.find((board) => board.name === 'Kanban').id
      workflowId = snapshot.workflows[0].id
    })
  })

  beforeEach(() => {
    errors = []
    trackConsole(errors)
    cy.viewport(1280, 800)
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('exibe um link para cada folha, sem elementos mortos, com tooltip e destaque ativo', () => {
    cy.visitApp(`/#/boards/${boardId}`)
    cy.get('.leaf').its('length').then((treeLeaves) => {
      collapse()

      cy.get('nav.rail').should('have.attr', 'aria-label', 'Pages')
      cy.get('nav.rail a.leaf.rail-leaf').should('have.length', treeLeaves)
      cy.get('nav.rail .rail-divider').should('have.length.greaterThan', 0)

      cy.get('nav.rail .rail-leaf svg').first().then(($svg) => {
        expect(Math.round($svg[0].getBoundingClientRect().width)).to.eq(18)
        expect(Math.round($svg[0].getBoundingClientRect().height)).to.eq(18)
      })

      cy.get('#app-sidebar').then(($sidebar) => {
        expect(Math.round($sidebar[0].getBoundingClientRect().width)).to.eq(48)
      })
      cy.get('nav.rail').then(($rail) => {
        expect($rail[0].scrollWidth).to.be.at.most($rail[0].clientWidth)
      })

      cy.get('nav.rail .nav-entry, nav.rail .chevron, nav.rail .icon-btn, nav.rail .badge-id, nav.rail .source-path, nav.rail .label, nav.rail .rail-leaf span').should('not.exist')
      cy.get('nav:not(.rail)').should('not.exist')
      cy.get('#app-sidebar .brand').then(($brand) => {
        expect($brand[0].getBoundingClientRect().width).to.eq(0)
      })

      cy.get('nav.rail a.rail-leaf').each(($link) => {
        expect($link.attr('title'), 'title').to.be.a('string').and.not.be.empty
        expect($link.attr('aria-label'), 'aria-label').to.eq($link.attr('title'))
      })

      expectOneCurrent('Kanban')

      cy.get('button.sidebar-toggle').click()
      cy.get('button.sidebar-toggle').should('have.attr', 'aria-expanded', 'true')
      cy.get('nav.rail').should('not.exist')
      cy.get('nav:not(.rail)').should('be.visible')
      cy.get('.leaf').should('have.length', treeLeaves)
    })
  })

  it('compara a contagem do rail com as folhas do modelo', () => {
    cy.visitApp('/')
    cy.get('.leaf').then(($tree) => {
      const treeTitles = [...$tree].map((link) => link.getAttribute('title'))
      expect(treeTitles).to.not.include('Experiences')
      cy.wrap(treeTitles.length).as('treeCount')
    })
    collapse()
    cy.get('@treeCount').then((treeCount) => {
      cy.get('nav.rail a.rail-leaf').should('have.length', treeCount)
    })
  })

  it('cada link do rail navega para o seu endereço e destaca apenas ele', () => {
    cy.visitApp('/')
    collapse()
    cy.get('nav.rail a.rail-leaf').each(($link) => {
      const title = $link.attr('title')
      const path = `#${$link[0].href.split('#')[1] || ''}`
      cy.wrap($link).click()
      cy.location('hash').should('eq', path)
      expectOneCurrent(title)
    })
  })

  it('mantém o destaque ao recarregar em deep link e ao clicar em outra folha', () => {
    cy.visitApp(`/#/workflows/${workflowId}/transitions`)
    collapse()
    expectOneCurrent('Standard · Transitions')
    cy.get('nav.rail a.rail-leaf[aria-current="page"]').should('have.class', 'router-link-active')

    cy.reload()
    cy.get('#app-sidebar').should('not.have.class', 'collapsed')
    collapse()
    expectOneCurrent('Standard · Transitions')

    cy.get('nav.rail a.rail-leaf[title="Kanban · Columns"]').click()
    cy.location('hash').should('eq', `#/boards/${boardId}/columns`)
    expectOneCurrent('Kanban · Columns')
  })

  it('navega entre os links com Tab e mostra o foco visível', () => {
    cy.visitApp(`/#/boards/${boardId}`)
    collapse()
    cy.get('button.sidebar-toggle').focus().then(($toggle) => {
      expect($toggle[0].matches(':focus')).to.eq(true)
      expect($toggle[0].matches(':focus-visible'), 'botão com foco visível').to.eq(true)
    })
    cy.get('nav.rail a.rail-leaf').first().focus()
    cy.focused().then(($first) => {
      expect($first.attr('title')).to.eq('Kanban')
      const outline = $first[0].ownerDocument.defaultView.getComputedStyle($first[0])
      expect(outline.outlineStyle).to.not.eq('none')
      expect(outline.outlineWidth).to.eq('2px')
    })
    cy.press(Cypress.Keyboard.Keys.TAB)
    cy.window().its('document.activeElement').then((active) => {
      expect(active.matches('a.leaf.rail-leaf'), 'segundo link com foco').to.eq(true)
      expect(active.textContent, 'texto').to.eq('')
      expect(active.querySelectorAll('svg').length, 'icones').to.eq(1)
    })
    cy.get('nav.rail a.rail-leaf').eq(1).then(($link) => {
      cy.wrap($link).click()
      cy.location('hash').should('eq', `#${$link[0].href.split('#')[1]}`)
      expectOneCurrent($link.attr('title'))
    })
  })

  it('destaca o board escolhido pela loja na rota padrão /#/boards', () => {
    cy.visitApp('/#/boards')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    collapse()
    expectOneCurrent('Kanban')
  })

  it('mantém a árvore expandida em 1024px e exibe o rail a partir de 1025px', () => {
    cy.viewport(1024, 800)
    cy.visitApp('/')
    cy.get('.leaf').should('have.length.greaterThan', 0)
    cy.get('button.sidebar-toggle').should('not.exist')
    cy.get('nav:not(.rail)').should('be.visible')
    cy.get('nav.rail').should('not.exist')

    cy.viewport(1025, 800)
    cy.window().then((win) => {
      win.dispatchEvent(new Event('resize'))
    })
    cy.get('button.sidebar-toggle').should('be.visible')
    cy.get('nav:not(.rail)').should('be.visible')
    cy.get('nav.rail').should('not.exist')

    cy.viewport(1024, 800)
    cy.window().then((win) => {
      win.dispatchEvent(new Event('resize'))
    })
    cy.get('button.sidebar-toggle').should('not.exist')
    cy.get('nav.rail').should('not.exist')
    cy.get('nav:not(.rail)').should('be.visible')

    cy.viewport(1025, 800)
    cy.window().then((win) => {
      win.dispatchEvent(new Event('resize'))
    })
    cy.get('button.sidebar-toggle').click()
    cy.get('#app-sidebar').should('have.class', 'collapsed')
    cy.get('nav.rail a.rail-leaf').should('have.length.greaterThan', 0)
  })
})

describe('destaque ativo com mais de uma entidade do mesmo tipo', () => {
  let errors
  let boardId

  before(() => {
    cy.wsSnapshot().then((snapshot) => {
      boardId = snapshot.boards.find((board) => board.name === 'Kanban').id
    })
  })

  beforeEach(() => {
    errors = []
    trackConsole(errors)
    cy.viewport(1280, 800)
    cy.intercept({ method: 'GET', pathname: '**/api/workspaces/*/snapshot' }, (req) => {
      req.continue((res) => {
        const workspaceId = res.body.workspaces[0].id
        const workflowId = res.body.workflows[0].id
        const statusId = res.body.statuses[0].id
        res.body.boards.push({
          id: 'B2',
          name: 'Board 2',
          workspaceId,
          workflowId,
          filters: { statusIds: [] },
          columns: [{ id: 'todo', name: 'Todo', wip: null, statusIds: [statusId] }],
        })
        res.body.workflows.push({ id: 'flow-b', name: 'Flow B', workspaceId })
        res.body.teams.push({ id: 'design', name: 'Design team', workspaceId })
      })
    })
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('destaca somente o board, o workflow e o time atuais', () => {
    cy.visitApp(`/#/boards/${boardId}`)
    cy.get('.leaf[title="Board 2"]').should('exist')
    collapse()
    expectOneCurrent('Kanban')

    cy.get('nav.rail a.rail-leaf[title="Board 2"]').click()
    cy.location('hash').should('eq', '#/boards/B2')
    expectOneCurrent('Board 2')

    cy.get('nav.rail a.rail-leaf[title="Flow B · Statuses"]').click()
    cy.location('hash').should('eq', '#/workflows/flow-b/statuses')
    expectOneCurrent('Flow B · Statuses')

    cy.get('nav.rail a.rail-leaf[title="Design team · Roles"]').click()
    cy.location('hash').should('eq', '#/teams/design/roles')
    expectOneCurrent('Design team · Roles')
  })
})
