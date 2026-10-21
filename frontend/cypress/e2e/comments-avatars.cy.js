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
    cy.request('GET', '/api/issues/KANB-1').then((response) => {
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
    cy.get('@seedComments').then((seedComments) => {
      cy.request('GET', '/api/issues/KANB-1').then((response) => {
        const current = JSON.stringify(response.body.comments)
        const seed = JSON.stringify(seedComments)
        if (current === seed) return undefined
        return cy.request({
          method: 'PATCH',
          url: '/api/issues/KANB-1',
          body: { comments: seedComments },
        })
      })
    })
  })

  it('o avatar do card é circular e mede 24px', () => {
    cardById('KANB-2')
      .find('.avatar')
      .should('have.length', 1)
      .and(($avatar) => {
        expect($avatar[0].getBoundingClientRect().width).to.equal(24)
        expect($avatar[0].getBoundingClientRect().height).to.equal(24)
        const style = window.getComputedStyle($avatar[0])
        expect(style.borderRadius).to.equal('50%')
      })
  })

  it('o card do assignee tem o título com o nome do agente', () => {
    cardById('KANB-2')
      .find('.avatar')
      .should('have.attr', 'title', 'Vivy')
  })

  it('o card sem assignee tem o título "Unassigned"', () => {
    cardById('KANB-6')
      .find('.avatar')
      .should('have.attr', 'title', 'Unassigned')
  })

  it('o comentário de admin mostra a identidade do user sem imagem', () => {
    cy.get('.issue-panel .comment').should('have.length', 4)
    cy.get('.issue-panel .comment')
      .first()
      .find('img')
      .should('not.exist')
    cy.get('.issue-panel .comment')
      .first()
      .find('.initials')
      .should('have.text', 'A')
    cy.get('.issue-panel .comment')
      .first()
      .find('.comment-author')
      .should('have.text', 'admin')
  })

  it('o comentário da Vivy mostra a miniatura do data URL', () => {
    cy.get('.issue-panel .comment')
      .eq(1)
      .find('img')
      .should('have.attr', 'src')
      .and('match', /^data:image\//)
    cy.get('.issue-panel .comment')
      .eq(1)
      .find('img')
      .then((images) => {
        expect(images[0].naturalWidth).to.be.greaterThan(0)
      })
    cy.get('.issue-panel .comment')
      .eq(1)
      .find('.comment-author')
      .should('have.text', 'Vivy')
  })

  it('o comentário do Kaio mostra a identidade do agente pelo autor', () => {
    cy.get('.issue-panel .comment')
      .eq(2)
      .find('img')
      .should('not.exist')
    cy.get('.issue-panel .comment')
      .eq(2)
      .find('.initials')
      .should('have.text', 'K')
    cy.get('.issue-panel .comment')
      .eq(2)
      .find('.comment-author')
      .should('have.text', 'Kaio')
  })

  it('o comentário antigo de user mostra inicial U e cor determinística', () => {
    cy.get('.issue-panel .comment')
      .eq(3)
      .find('img')
      .should('not.exist')
    cy.get('.issue-panel .comment')
      .eq(3)
      .find('.initials')
      .should('have.text', 'U')
    cy.get('.issue-panel .comment')
      .eq(3)
      .find('.avatar')
      .should(($avatar) => {
        const background = window.getComputedStyle($avatar[0]).backgroundColor
        expect(background).to.not.equal('rgba(0, 0, 0, 0)')
      })
    cy.get('.issue-panel .comment')
      .eq(3)
      .find('.comment-author')
      .should('have.text', 'user')
  })

  it('a legenda junta o nome e o timestamp no formato dd/MM/yyyy HH:mm', () => {
    cy.get('.issue-panel .comment .comment-author-info .muted.small').should(
      ($stamps) => {
        expect($stamps.length).to.equal(4)
        $stamps.each((_index, element) => {
          expect(element.textContent, element.textContent).to.match(TIMESTAMP_PATTERN)
        })
      },
    )
  })

  it('os comentários do seed aparecem em ordem cronológica', () => {
    cy.get('.issue-panel .comment .comment-author').should(($authors) => {
      const names = [...$authors].map((element) => element.textContent)
      expect(names).to.deep.equal(['admin', 'Vivy', 'Kaio', 'user'])
    })
  })

  it('o comentário publicado logado surge com admin e preserva a ordem', () => {
    cy.intercept('POST', '/api/issues/KANB-1/comments').as('postComment')
    cy.get('.issue-panel textarea[aria-label="Comment"]')
      .type('E2E KAN-128: comentário publicado logado')
    cy.get('.issue-panel').contains('button', 'Comment').click()
    cy.wait('@postComment').its('request.body').its('author').should('equal', 'admin')
    cy.get('.issue-panel .comment').should('have.length', 5)
    cy.get('.issue-panel .comment .comment-author').should(($authors) => {
      const names = [...$authors].map((element) => element.textContent)
      expect(names).to.deep.equal(['admin', 'Vivy', 'Kaio', 'user', 'admin'])
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
