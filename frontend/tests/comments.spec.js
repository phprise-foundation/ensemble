import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import IssueCard from '../src/components/IssueCard.vue'
import IssuePanel from '../src/components/IssuePanel.vue'
import { session } from '../src/store/session.js'
import { api } from '../src/api/index.js'

const timestampPattern = /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/

const client = vi.hoisted(() => ({ tokenValue: 't', request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  token: () => client.tokenValue,
  setToken: (value) => {
    client.tokenValue = value
  },
  request: (...args) => client.request(...args),
}))

vi.mock('../src/store/board.js', async () => {
  const { ref } = await import('vue')
  const issue = {
    id: 'KANB-1',
    statusId: 'in-progress',
    assigneeId: 'vivy',
    summary: 'Avatares circulares e comentários com autor',
    description: 'RFC KAN-128',
    comments: [
      { id: 'c1', author: 'vivy', body: 'Comentário do agente por id', at: '2026-10-19T12:07:00Z' },
      { id: 'c2', author: 'user', body: 'Comentário legado', at: '2026-10-19T09:00:00Z' },
    ],
  }
  return {
    boardStore: {
      state: {
        agents: [{ id: 'vivy', name: 'Vivy', avatarColor: '#4c9aff', portrait: 'data:image/png;base64,iVBORw0KGgo=' }],
      },
      selectedIssue: ref(issue),
      status: () => ({ id: 'in-progress', name: 'In Progress', color: '#0052cc' }),
      agent: (id) => (id === 'vivy' ? { id: 'vivy', name: 'Vivy', roleIds: [], avatarColor: '#4c9aff', portrait: 'data:image/png;base64,iVBORw0KGgo=' } : null),
      role: () => ({ name: 'Product Owner' }),
      userTargets: () => [],
      isOverdue: () => false,
      simulated: () => false,
      columnOf: () => null,
      moveIssue: vi.fn(),
      updateIssue: vi.fn(),
      addComment: vi.fn(),
      select: vi.fn(),
      clearSelection: vi.fn(),
    },
  }
})

describe('IssuePanel: comentarios com autor e avatares (KAN-128)', () => {
  it('[RED] cada comentario tem miniatura do autor, legenda com nome e timestamp absoluto', () => {
    const panel = mount(IssuePanel)
    const rows = panel.findAll('.comment')
    expect(rows.length).toBe(2)

    for (const row of rows) {
      expect(row.find('.avatar').exists(), 'a miniatura circular do autor deve existir na linha').toBe(true)
    }

    for (const row of rows) {
      const rendered = row.findAll('span').map((span) => span.text())
      expect(rendered.some((text) => timestampPattern.test(text)), `a linha deve ter timestamp dd/MM/yyyy HH:mm; spans: ${rendered.join(' | ')}`).toBe(true)
    }
  })

  it('[RED] agente resolve o nome pelo id e o autor legado cai no fallback com a string original', () => {
    const panel = mount(IssuePanel)
    const rows = panel.findAll('.comment')
    expect(rows[0].get('.comment-author').text()).toBe('Vivy', `o author "vivy" resolve para o agente; obtido: ${rows[0].text()}`)
    expect(rows[1].get('.comment-author').text()).toBe('user', 'o author legado "user" mantem a string original e cai no fallback')
    expect(panel.find('img[src=""]').exists()).toBe(false, 'nenhuma img com src vazio (imagem quebrada impedida)')
    expect(panel.findAll('.avatar').length).toBeGreaterThan(0, 'o painel deve renderizar miniaturas via Avatar')
  })
})

describe('IssueCard: assignee renderiza via Avatar (KAN-128)', () => {
  const issue = { id: 'KANB-2', statusId: 'in-progress', assigneeId: 'vivy', summary: 'Card com agente' }

  it('[RED] assignee atribuido renderiza o Avatar circular com tooltip do nome do agente', () => {
    const card = mount(IssueCard, { props: { issue } })
    const avatar = card.find('.avatar')
    expect(avatar.exists(), 'o card deve renderizar o assignee via componente Avatar').toBe(true)
    expect(avatar.classes()).toContain('sm')
    expect(avatar.attributes('title'), 'o tooltip deve carregar o nome do agente').toBe('Vivy')
    expect(avatar.attributes('aria-label'), 'o Avatar nao-decorativo rotula por aria-label').toBe('Avatar de Vivy')
  })

  it('[RED] card sem assignee cai no fallback neutro com tooltip Unassigned', () => {
    const card = mount(IssueCard, { props: { issue: { ...issue, id: 'KANB-3', assigneeId: null } } })
    const avatar = card.find('.avatar')
    expect(avatar.attributes('title'), 'sem agente, o tooltip e o termo do painel').toBe('Unassigned')
    expect(avatar.text(), 'o fallback neutro mostra "?" como conteudo').toBe('?')
  })
})

describe('store real: contrato do POST de comentarios (KAN-128 onda 1)', () => {
  it('addComment posta so o body no endpoint por key; o autor e do lado do servidor', async () => {
    const { boardStore } = await vi.importActual('../src/store/board.js')
    boardStore.state.issues = [{ id: 'i1', key: 'KANB-1', comments: [] }]
    client.request.mockResolvedValue({ issue: { id: 'i1', key: 'KANB-1', comments: [{ id: 'c3', author: 'admin', body: 'Novo', at: '2026-10-19T13:00:00Z' }] } })

    await boardStore.addComment('i1', 'Comentario novo')

    const post = client.request.mock.calls.find(([method, path]) => method === 'POST' && path.includes('/issues/KANB-1/comments'))
    expect(post).toBeTruthy()
    expect(post[2]).toEqual({ body: 'Comentario novo' })
  })
})
