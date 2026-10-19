Cypress.Screenshot.defaults({ screenshotOnRunFailure: false })

const collectErrors = (win, errors) => {
  win.addEventListener('error', (event) => errors.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) =>
    errors.push(`unhandledrejection: ${event.reason}`),
  )
}

const ESCALA = [14, 16, 18, 20]

function temEscalaCorreta(jquery) {
  const svg = jquery[0]
  const caixa = svg.getBoundingClientRect()
  if (caixa.width === 0 && caixa.height === 0) return
  const tamanho = Number(svg.getAttribute('width'))
  const altura = Number(svg.getAttribute('height'))
  const traço = Number(getComputedStyle(svg).strokeWidth)
  expect(ESCALA, `tamanho ${tamanho} fora da escala`).to.include(tamanho)
  expect(altura, 'altura igual à largura').to.eq(tamanho)
  expect(caixa.width, 'largura renderizada').to.be.within(tamanho - 8, tamanho + 0.6)
  expect(Number.isNaN(traço) ? Number(svg.getAttribute('stroke-width')) : traço).to.eq(2)
}

function auditaIcones(jquery, nomeContexto) {
  expect(jquery.length, `${nomeContexto}: nenhum ícone encontrado`).to.be.greaterThan(0)
  jquery.each((index, svg) => {
    temEscalaCorreta(Cypress.$(svg))
  })
}

function classeLucide(jquery, glifo) {
  const svg = jquery[0]
  expect(svg.classList.contains('lucide'), 'classe lucide ausente').to.be.true
  expect(svg.classList.contains(`lucide-${glifo}`), `classe lucide-${glifo} ausente`).to.be.true
}

const GLIFOS = {
  board: 'kanban',
  workspace: 'app-window',
  folder: 'folder',
  layers: 'layers',
  agents: 'users',
  roles: 'shield',
  profiles: 'star',
  statuses: 'clock',
  transitions: 'arrow-right',
  issues: 'file-text',
  experiences: 'bookmark',
  plus: 'plus',
  chevron: 'chevron-right',
  sun: 'sun',
  moon: 'moon',
  close: 'x',
  delete: 'trash-2',
  edit: 'pencil',
  check: 'check',
  menu: 'menu',
  comment: 'message-square',
  warning: 'triangle-alert',
  sidebar: 'panel-left',
  copilot: 'panel-right',
  console: 'square-terminal',
}

describe('padrão de ícones Lucide na interface em execução (KAN-106)', () => {
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
    cy.visit('/')
    cy.get('.topbar', { timeout: 15000 }).should('be.visible')
  })

  afterEach(() => {
    cy.get('vite-error-overlay').should('not.exist')
    cy.then(() => {
      expect(errors, `erros de runtime:\n${errors.join('\n')}`).to.have.length(0)
    })
  })

  it('topbar: ícones do menu, console e copilot com tamanho, traço e nome acessível', () => {
    cy.screenshot('topbar-padrao')
    cy.get('.topbar .icon-btn').then(($botoes) => {
      expect($botoes.length, 'botões de ícone no topo').to.be.greaterThan(0)
      $botoes.each((index, botao) => {
        expect(botao.getAttribute('aria-label'), `aria-label do botão ${index}`).to.be.ok
      })
    })
    cy.get('.topbar .icon-btn svg').then(($svgs) => {
      auditaIcones($svgs, 'topbar')
      $svgs.each((index, svg) => {
        expect(svg.getAttribute('aria-hidden'), `aria-hidden do ícone ${index}`).to.eq('true')
      })
    })
    cy.get('.topbar .icon-btn.menu-toggle svg').then(($svg) => classeLucide($svg, GLIFOS.menu))
    cy.get('.topbar .icon-btn.console-toggle svg').then(($svg) => classeLucide($svg, GLIFOS.console))
    cy.get('.topbar .icon-btn.copilot-toggle svg').then(($svg) => classeLucide($svg, GLIFOS.copilot))
  })

  it('sidebar: marca smartboard fora do wrapper Lucide, escala limpa e chevron distinto do plus', () => {
    cy.get('.sidebar').should('exist')
    cy.get('.sidebar .brand-icon svg').then(($marca) => {
      expect($marca[0].classList.contains('lucide'), 'marca não deve usar Lucide').to.be.false
    })
    cy.get('.sidebar .lucide').then(($svgs) => {
      auditaIcones($svgs, 'sidebar')
      const tamanhos = Cypress._.uniq(
        $svgs.toArray().map((svg) => Number(svg.getAttribute('width'))),
      )
      expect(tamanhos, 'só a escala 14/16/18/20 no sidebar').to.have.members(
        tamanhos.filter((tamanho) => ESCALA.includes(tamanho)),
      )
    })
    cy.get('.sidebar .icon-btn').then(($botoes) => {
      expect($botoes.length, 'botões de ícone no sidebar').to.be.greaterThan(0)
      $botoes.each((index, botao) => {
        expect(botao.getAttribute('aria-label'), `aria-label do botão ${index}`).to.be.ok
      })
    })
    cy.get('.sidebar .lucide-chevron-right').first().then(($chevron) => {
      expect($chevron[0].getAttribute('width')).to.eq('14')
    })
    cy.screenshot('sidebar-padrao')
  })

  it('CRUD de agents: editar é lápis, excluir é lixeira e os glifos são distintos', () => {
    cy.get('.sidebar a.leaf', { timeout: 15000 }).contains('Agents').click()
    cy.url().should('include', '/teams/')
    cy.get('[aria-label="Edit"] svg').then(($lapis) => {
      classeLucide($lapis, GLIFOS.edit)
      expect($lapis[0].getAttribute('width')).to.eq('14')
    })
    cy.get('[aria-label="Delete"] svg').then(($lixeira) => {
      classeLucide($lixeira, GLIFOS.delete)
      expect($lixeira[0].getAttribute('width')).to.eq('14')
    })
    cy.get('.lucide-pencil, .lucide-trash-2').then(($glifos) => {
      expect($glifos.length, 'pencil e trash-2 presentes').to.be.at.least(2)
    })
    cy.get('.lucide-pencil').first().then(($lapis) => {
      cy.get('.lucide-trash-2').first().then(($lixeira) => {
        expect($lixeira[0].innerHTML, 'editar e excluir devem ser visualmente distintos').to.not.eq(
          $lapis[0].innerHTML,
        )
      })
    })
    cy.screenshot('crud-agents-lista')
    cy.get('[aria-label="Edit"]').first().click()
    cy.get('svg.lucide-x').then(($fechar) => {
      classeLucide($fechar, GLIFOS.close)
      expect($fechar[0].getAttribute('width')).to.eq('20')
      expect($fechar.closest('button').attr('aria-label')).to.eq('Close')
    })
    cy.get('.lucide-check').then(($check) => {
      expect($check[0].getAttribute('width')).to.eq('16')
    })
    cy.get('.lucide-x').first().then(($fechar) => {
      cy.get('.lucide-check').first().then(($check) => {
        expect($check[0].innerHTML, 'fechar e salvar devem ser visualmente distintos').to.not.eq(
          $fechar[0].innerHTML,
        )
      })
    })
    cy.screenshot('crud-agents-modal')
  })

  it('CRUD de issue types: mesmo padrão de editar/excluir, remover e adicionar campo', () => {
    cy.get('.sidebar a.leaf', { timeout: 15000 }).contains('Issue types').click()
    cy.url().should('include', 'issue-types')
    cy.get('[aria-label="Edit"] svg').then(($lapis) => classeLucide($lapis, GLIFOS.edit))
    cy.get('[aria-label="Delete"] svg').then(($lixeira) => classeLucide($lixeira, GLIFOS.delete))
    cy.screenshot('crud-issue-types-lista')
    cy.get('[aria-label="Edit"]').first().click()
    cy.get('svg.lucide-x').closest('button').should('have.attr', 'aria-label', 'Close')
    cy.get('[aria-label="Remove"] svg').then(($remover) => classeLucide($remover, GLIFOS.delete))
    cy.get('button:contains("Add field") svg').then(($plus) => classeLucide($plus, GLIFOS.plus))
    cy.screenshot('crud-issue-types-modal')
  })

  it('board e painel do issue: plus do New task e fechar do painel', () => {
    cy.get('.card', { timeout: 15000 }).should('have.length.at.least', 1)
    cy.get('button:contains("New task") svg').then(($plus) => {
      classeLucide($plus, GLIFOS.plus)
      expect($plus[0].getAttribute('width')).to.eq('16')
    })
    cy.get('.card').first().click()
    cy.get('.issue-panel').should('be.visible')
    cy.get('.issue-panel .icon-btn svg').then(($fechar) => {
      classeLucide($fechar, GLIFOS.close)
      expect($fechar[0].getAttribute('width')).to.eq('20')
      expect($fechar.closest('button').attr('aria-label')).to.eq('Close')
    })
    cy.get('.issue-panel .lucide').then(($svgs) => auditaIcones($svgs, 'painel do issue'))
    cy.screenshot('painel-issue')
  })

  it('1024x800: sem layout quebrado no corte do sidebar', () => {
    cy.viewport(1024, 800)
    cy.visit('/')
    cy.get('.topbar').should('be.visible')
    cy.document().then((doc) => {
      expect(doc.documentElement.scrollWidth, 'sem barra horizontal').to.be.lessThan(1100)
    })
    cy.screenshot('corte-1024')
  })
})
