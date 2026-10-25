import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

vi.mock('../src/store/board.js', async () => {
  const { computed, reactive, ref } = await import('vue')
  const selectedIssue = ref(null)
  const statuses = [
    { id: 'to-develop', name: 'To Develop' },
    { id: 'in-development', name: 'In Development' },
  ]
  const agents = [
    { id: 'vivy', name: 'Vivy', roleIds: ['po'] },
    { id: 'kaio', name: 'Kaio', roleIds: ['coder'] },
  ]
  const roles = [{ id: 'coder', name: 'Coder' }]
  return {
    boardStore: {
      selectedIssue,
      state: reactive({ agents }),
      status: (id) => statuses.find((status) => status.id === id),
      issueType: () => ({ fields: [] }),
      agent: (id) => agents.find((agent) => agent.id === id),
      role: (id) => roles.find((role) => role.id === id),
      userTargets: () => statuses,
      isOverdue: () => false,
      clearSelection: vi.fn(),
      moveIssue: vi.fn(),
      updateIssue: vi.fn(),
      addComment: vi.fn(),
    },
  }
})

import { boardStore } from '../src/store/board.js'
import IssuePanel from '../src/components/IssuePanel.vue'
import IssueHistory from '../src/components/IssueHistory.vue'

const ISO_OLD = '2024-01-01T10:00:00.000Z'
const ISO_MID = '2024-01-02T10:00:00.000Z'
const ISO_NEW = '2024-01-03T10:00:00.000Z'

function makeIssue(overrides = {}) {
  return {
    id: 'KANB-1',
    summary: 'Frame the onboarding intent',
    description: 'The client wants a smoother onboarding.',
    statusId: 'to-develop',
    assigneeId: 'vivy',
    comments: [],
    history: [],
    ...overrides,
  }
}

const richHistory = () => [
  { type: 'created', actor: 'user', at: ISO_OLD, summary: 'Issue created' },
  { type: 'status', actor: 'user', at: ISO_MID, from: 'to-develop', to: 'in-development', summary: 'Issue moved' },
  { type: 'assignee', actor: 'Vivy', at: ISO_NEW, from: null, to: 'kaio' },
  { type: 'summary', actor: 'user', at: ISO_OLD, from: 'Old', to: 'Frame the onboarding intent', summary: 'Summary narrowed' },
  { type: 'description', actor: 'user', at: ISO_MID, summary: 'Description updated' },
]

function mountPanel() {
  return mount(IssuePanel, { attachTo: document.body })
}

async function openHistoryTab(wrapper) {
  await wrapper.find('#issue-tab-history').trigger('click')
  await nextTick()
}

describe('IssuePanel abas Comments e History', () => {
  afterEach(() => {
    boardStore.selectedIssue.value = null
    document.body.innerHTML = ''
  })

  it('Comments é a aba padrão, contador só nela, History oculta', async () => {
    boardStore.selectedIssue.value = makeIssue({ comments: [{ id: 'c1', author: 'user', body: 'oi', at: ISO_OLD }] })
    const wrapper = mountPanel()
    await nextTick()

    const commentsTab = wrapper.find('#issue-tab-comments')
    const historyTab = wrapper.find('#issue-tab-history')
    expect(commentsTab.attributes('aria-selected')).toBe('true')
    expect(historyTab.attributes('aria-selected')).toBe('false')
    expect(commentsTab.text()).toContain('Comments (1)')
    expect(historyTab.text()).not.toMatch(/Comments? \(\d\)/)
    expect(wrapper.find('#issue-panel-comments').isVisible()).toBe(true)
    expect(wrapper.find('#issue-panel-history').isVisible()).toBe(false)
    wrapper.unmount()
  })

  it('clique em History seleciona a aba, mostra a timeline e volta ao clicar em Comments', async () => {
    boardStore.selectedIssue.value = makeIssue({ history: richHistory() })
    const wrapper = mountPanel()
    await nextTick()

    await openHistoryTab(wrapper)
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')
    expect(wrapper.find('#issue-panel-history').isVisible()).toBe(true)
    expect(wrapper.find('#issue-panel-comments').isVisible()).toBe(false)

    await wrapper.find('#issue-tab-comments').trigger('click')
    await nextTick()
    expect(wrapper.find('#issue-tab-comments').attributes('aria-selected')).toBe('true')
    wrapper.unmount()
  })

  it('tabindex roving: 0 na aba selecionada e -1 na outra', async () => {
    boardStore.selectedIssue.value = makeIssue()
    const wrapper = mountPanel()
    await nextTick()

    expect(wrapper.find('#issue-tab-comments').attributes('tabindex')).toBe('0')
    expect(wrapper.find('#issue-tab-history').attributes('tabindex')).toBe('-1')
    wrapper.unmount()
  })

  it('cada aba declara aria-controls e cada painel declara aria-labelledby', async () => {
    boardStore.selectedIssue.value = makeIssue()
    const wrapper = mountPanel()
    await nextTick()

    expect(wrapper.find('#issue-tab-comments').attributes('aria-controls')).toBe('issue-panel-comments')
    expect(wrapper.find('#issue-tab-history').attributes('aria-controls')).toBe('issue-panel-history')
    expect(wrapper.find('#issue-panel-comments').attributes('aria-labelledby')).toBe('issue-tab-comments')
    expect(wrapper.find('#issue-panel-history').attributes('aria-labelledby')).toBe('issue-tab-history')
    wrapper.unmount()
  })

  it('setas com wrap, Home e End selecionam a aba correta com foco', async () => {
    boardStore.selectedIssue.value = makeIssue()
    const wrapper = mountPanel()
    await nextTick()

    const commentsTab = wrapper.find('#issue-tab-comments')
    const historyTab = wrapper.find('#issue-tab-history')

    await commentsTab.trigger('keydown', { key: 'ArrowRight' })
    await nextTick()
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')
    expect(document.activeElement?.id).toBe('issue-tab-history')

    await wrapper.find('#issue-tab-history').trigger('keydown', { key: 'ArrowRight' })
    await nextTick()
    expect(wrapper.find('#issue-tab-comments').attributes('aria-selected')).toBe('true')
    expect(document.activeElement?.id).toBe('issue-tab-comments')

    await wrapper.find('#issue-tab-comments').trigger('keydown', { key: 'ArrowLeft' })
    await nextTick()
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')

    await historyTab.trigger('keydown', { key: 'Home' })
    await nextTick()
    expect(wrapper.find('#issue-tab-comments').attributes('aria-selected')).toBe('true')

    await commentsTab.trigger('keydown', { key: 'End' })
    await nextTick()
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')

    wrapper.unmount()
  })

  it('Enter e Espaço acionam a aba focada', async () => {
    boardStore.selectedIssue.value = makeIssue()
    const wrapper = mountPanel()
    await nextTick()

    await wrapper.find('#issue-tab-history').trigger('keydown', { key: 'Enter' })
    await nextTick()
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')

    await wrapper.find('#issue-tab-history').trigger('keydown', { key: 'ArrowLeft' })
    await nextTick()
    await wrapper.find('#issue-tab-comments').trigger('keydown', { key: 'ArrowLeft' })
    await nextTick()
    await wrapper.find('#issue-tab-history').trigger('keydown', { key: ' ' })
    await nextTick()
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')

    wrapper.unmount()
  })

  it('trocar de issue reseta a aba ativa para Comments', async () => {
    boardStore.selectedIssue.value = makeIssue({ id: 'KANB-1', history: richHistory() })
    const wrapper = mountPanel()
    await nextTick()
    await openHistoryTab(wrapper)
    expect(wrapper.find('#issue-tab-history').attributes('aria-selected')).toBe('true')

    boardStore.selectedIssue.value = makeIssue({ id: 'KANB-2' })
    await nextTick()
    expect(wrapper.find('#issue-tab-comments').attributes('aria-selected')).toBe('true')
    expect(wrapper.find('#issue-panel-comments').isVisible()).toBe(true)
    wrapper.unmount()
  })

  it('issue com history vazia mostra o empty state na History', async () => {
    boardStore.selectedIssue.value = makeIssue({ history: [] })
    const wrapper = mountPanel()
    await nextTick()
    await openHistoryTab(wrapper)

    const historyPanel = wrapper.find('#issue-panel-history')
    expect(historyPanel.text()).toContain('No history yet')
    expect(historyPanel.find('.history-list').exists()).toBe(false)
    wrapper.unmount()
  })

})

describe('IssueHistory timeline', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('ordena em cronologia inversa pelo datetime, não pelo texto localizado', () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [
        { type: 'created', actor: 'user', at: ISO_MID, summary: 'meio' },
        { type: 'created', actor: 'user', at: ISO_NEW, summary: 'novo' },
        { type: 'created', actor: 'user', at: ISO_OLD, summary: 'velho' },
      ] },
    })
    const summaries = wrapper.findAll('.history-text').map((node) => node.text())
    expect(summaries[0]).toContain('novo')
    expect(summaries[1]).toContain('meio')
    expect(summaries[2]).toContain('velho')
    wrapper.unmount()
  })

  it('cada datahora renderiza um <time datetime> com o ISO original', () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [{ type: 'created', actor: 'user', at: ISO_MID, summary: 'x' }] },
    })
    const time = wrapper.find('time')
    expect(time.attributes('datetime')).toBe(ISO_MID)
    wrapper.unmount()
  })

  it('os cinco tipos usam o ícone e a data-history-type corretos', () => {
    const wrapper = mount(IssueHistory, { props: { events: richHistory() } })
    const types = wrapper.findAll('.history-entry').map((node) => node.attributes('data-history-type'))
    for (const type of ['created', 'status', 'assignee', 'summary', 'description']) {
      expect(types).toContain(type)
    }
    expect(wrapper.find('.history-icon svg').exists()).toBe(true)
    wrapper.unmount()
  })

  it('resolve nomes de status e agente e cai para o valor bruto em id desconhecido', () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [
        { type: 'status', actor: 'user', at: ISO_NEW, from: 'to-develop', to: 'in-development' },
        { type: 'status', actor: 'Kaio', at: ISO_MID, from: 'ghost', to: 'phantom' },
        { type: 'assignee', actor: 'Vivy', at: ISO_OLD, from: null, to: 'kaio' },
        { type: 'assignee', actor: 'user', at: ISO_OLD, from: 'kaio', to: null },
      ] },
    })
    const texts = wrapper.findAll('.history-text').map((node) => node.text())
    expect(texts[0]).toContain('To Develop → In Development')
    expect(texts[1]).toContain('ghost → phantom')
    expect(texts[2]).toContain('Unassigned → Kaio')
    expect(texts[3]).toContain('Kaio → Unassigned')
    wrapper.unmount()
  })

  it('evento sem type mas com from/to vira transição de status', () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [{ from: 'to-develop', to: 'in-development', actor: 'user', at: ISO_NEW }] },
    })
    const entry = wrapper.find('.history-entry')
    expect(entry.attributes('data-history-type')).toBe('status')
    expect(entry.text()).toContain('To Develop → In Development')
    wrapper.unmount()
  })

  it('nenhum comentário é renderizado dentro da History', () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [
        { type: 'created', actor: 'user', at: ISO_NEW, summary: 'comentário que é evento' },
      ] },
    })
    expect(wrapper.find('.comment').exists()).toBe(false)
    expect(wrapper.find('textarea').exists()).toBe(false)
    wrapper.unmount()
  })

  it('autores com id de agente aparecem como texto do actor, e actor vazio cai para —', async () => {
    const wrapper = mount(IssueHistory, {
      props: { events: [
        { type: 'created', actor: 'kaio', at: ISO_MID, summary: 'a' },
        { type: 'created', actor: '', at: ISO_OLD, summary: 'b' },
      ] },
    })
    const actors = wrapper.findAll('.history-actor').map((node) => node.text())
    expect(actors[0]).toBe('kaio')
    expect(actors[1]).toBe('—')
    wrapper.unmount()
  })
})
