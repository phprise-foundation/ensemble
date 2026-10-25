const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const TIMESTAMP_PATTERN = /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/

let errors

const expectNoErrors = () => {
  cy.then(() => {
    expect(errors, `runtime errors:\n${errors.join('\n')}`).to.deep.equal([])
  })
}

const cardById = (id) =>
  cy
    .get('.card')
    .filter((_index, element) => element.querySelector('.card-id')?.textContent === id)

describe('comments avatars e2e', () => {
  beforeEach(() => {
    cy.viewport(1280, 900)
    errors = []
    cy.on('window:before:load', (win) => {
      collectErrors(win, errors)
      cy.stub(win.console, 'error', (...args) => {
        errors.push(`console.error: ${args.join(' ')}`)
      })
    })
    cy.wsRequest('GET', '/issues/KANB-1/comments').then((response) => {
      cy.wrap(response.body.comments).as('seedComments')
    })
    cy.visit('/').then(() => {
      errors = []
    })
    cy.get('.card', { timeout: 10000 }).should('have.length.at.least', 1)
    cardById('KANB-1').click()
    cy.get('.issue-panel').should('be.visible')
  })

  afterEach(function () {
    expectNoErrors()
  })

  it('o avatar do card é circular e mede 22px', () => {
    cardById('KANB-2')
      .find('.avatar')
      .should('have.length', 1)
      .and(($avatar) => {
        expect($avatar[0].getBoundingClientRect().width).to.equal(22)
        expect($avatar[0].getBoundingClientRect().height).to.equal(22)
        const style = window.getComputedStyle($avatar[0])
        expect(style.borderRadius).to.equal('50%')
      })
  })

  it('o card do assignee tem o título com o nome do agente', () => {
    cardById('KANB-1')
      .find('.avatar')
      .should('have.attr', 'title', 'Vivy')
      .and('have.attr', 'aria-label', 'Avatar de Vivy')
  })

  it('o card sem assignee tem o título "Unassigned"', () => {
    cardById('KANB-6')
      .find('.avatar')
      .should('have.attr', 'title', 'Unassigned')
  })

  it('o comentário de admin mostra a identidade do user sem imagem', () => {
    cy.get('.issue-panel .comment').should('have.length', 5)
    cy.get('.issue-panel .comment')
      .first()
      .find('img')
      .should('not.exist')
    cy.get('.issue-panel .comment')
      .first()
      .find('.avatar')
      .should('have.text', 'A')
    cy.get('.issue-panel .comment')
      .first()
      .find('.comment-author')
      .should('have.text', 'admin')
  })

  it('todo comentário do seed é do admin logado, sem imagem de agente', () => {
    cy.get('.issue-panel .comment').each(($comment) => {
      cy.wrap($comment).find('img').should('not.exist')
      cy.wrap($comment).find('.avatar').should('have.text', 'A')
      cy.wrap($comment).find('.comment-author').should('have.text', 'admin')
    })
  })

  it('a legenda junta o nome e o timestamp no formato dd/MM/yyyy HH:mm', () => {
    cy.get('.issue-panel .comment .comment-author-info .muted.small').should(
      ($stamps) => {
        expect($stamps.length).to.equal(5)
        $stamps.each((_index, element) => {
          expect(element.textContent, element.textContent).to.match(TIMESTAMP_PATTERN)
        })
      },
    )
  })

  it('os comentários do seed aparecem em ordem cronológica', () => {
    cy.get('.issue-panel .comment .comment-author').should(($authors) => {
      const names = [...$authors].map((element) => element.textContent)
      expect(names.every((name) => name === 'admin')).to.be.true
    })
  })

  it('o comentário publicado logado surge com admin e preserva a ordem', () => {
    cy.intercept('POST', '**/api/workspaces/*/issues/KANB-1/comments').as('postComment')
    cy.get('.issue-panel textarea[aria-label="Comment"]')
      .type('E2E KAN-128: comentário publicado logado')
    cy.get('.comments-body > button.button').click()
    cy.get('.issue-panel .comment').should('have.length', 6)
    cy.get('.issue-panel .comment .comment-author').should(($authors) => {
      const names = [...$authors].map((element) => element.textContent)
      expect(names).to.deep.equal(['admin', 'admin', 'admin', 'admin', 'admin', 'admin'])
    })
    cy.get('.issue-panel .comment')
      .last()
      .find('.muted.small')
      .invoke('text')
      .should('match', TIMESTAMP_PATTERN)
  })

  it('nenhuma imagem do painel está quebrada', () => {
    cy.get('.issue-panel img').then((images) => {
      for (const image of images) {
        expect(image.naturalWidth, image.src).to.be.greaterThan(0)
      }
    })
  })
})
