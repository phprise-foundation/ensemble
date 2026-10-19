import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

const { state, board, lookups } = await vi.hoisted(async () => {
  const { reactive, computed } = await import('vue')
  const state = reactive({
    loading: false,
    error: '',
    issues: [],
    boards: [],
    statuses: [],
    agents: [],
    boardId: null,
  })
  const board = computed(() => state.boards.find((item) => item.id === state.boardId) ?? null)
  const lookups = {
    boardColumns: computed(() => board.value?.columns ?? []),
    boardStatuses: computed(() => state.statuses.filter((status) => status.workflowId === board.value?.workflowId)),
    boardIssueTypes: computed(() => []),
  }
  return { state, board, lookups }
})

vi.mock('../src/store/board.js', () => ({
  boardStore: {
    state,
    board,
    boardColumns: lookups.boardColumns,
    boardStatuses: lookups.boardStatuses,
    boardIssueTypes: lookups.boardIssueTypes,
    issuesForColumn: () => [],
    canEnterColumn: () => false,
    canUserMove: () => false,
    simulated: () => false,
    setBoard: () => {},
    createIssue: vi.fn().mockResolvedValue({ id: 'KAN-1' }),
    moveIssue: vi.fn(),
  },
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { boardId: null } }),
}))

import BoardPage from '../src/views/BoardPage.vue'

const mountBoardPage = () => mount(BoardPage, {
  global: {
    stubs: { BoardColumn: true, IssuePanel: true, AppIcon: 'span' },
  },
})

const seedBoard = () => {
  state.boardId = 'board-1'
  state.boards = [{ id: 'board-1', workflowId: 'wf-1', name: 'Board', columns: [] }]
}

async function openModalDuringLoading(wrapper) {
  await wrapper.find('.board-toolbar .button').trigger('click')
}

function select(wrapper) {
  return wrapper.find('#create-status')
}

describe('BoardPage: default do status no modal de nova task', () => {
  it('reaplica o primeiro initial quando os statuses chegam com o modal aberto', async () => {
    const wrapper = mountBoardPage()
    await openModalDuringLoading(wrapper)
    expect(select(wrapper).element.selectedIndex).toBe(-1)

    seedBoard()
    state.statuses = [
      { id: 's-final', workflowId: 'wf-1', type: 'final', name: 'Done' },
      { id: 's-dev', workflowId: 'wf-1', type: 'initial', name: 'To develop' },
    ]

    await nextTick()
    await nextTick()
    expect(select(wrapper).element.value).toBe('s-dev')
    expect(select(wrapper).element.selectedIndex).toBe(0)
    expect([...select(wrapper).element.options].map((option) => option.value)).toEqual(['s-dev'])
    wrapper.unmount()
  })

  it('mantém o status já escolhido quando a lista de initial muda', async () => {
    const wrapper = mountBoardPage()
    await openModalDuringLoading(wrapper)

    seedBoard()
    state.statuses = [
      { id: 's-dev', workflowId: 'wf-1', type: 'initial', name: 'To develop' },
      { id: 's-review', workflowId: 'wf-1', type: 'initial', name: 'Review' },
    ]
    await nextTick()
    await nextTick()
    expect(select(wrapper).element.value).toBe('s-dev')

    select(wrapper).element.value = 's-review'
    await select(wrapper).setValue('s-review')
    await nextTick()

    state.statuses = [...state.statuses, { id: 's-done', workflowId: 'wf-1', type: 'final', name: 'Done' }]
    await nextTick()
    await nextTick()
    expect(select(wrapper).element.value).toBe('s-review')
    wrapper.unmount()
  })

  it('zera o default quando o workflow perde o último initial', async () => {
    const wrapper = mountBoardPage()
    await openModalDuringLoading(wrapper)

    seedBoard()
    state.statuses = [{ id: 's-dev', workflowId: 'wf-1', type: 'initial', name: 'To develop' }]
    await nextTick()
    await nextTick()
    expect(select(wrapper).element.value).toBe('s-dev')

    state.statuses = []
    await nextTick()
    await nextTick()
    expect(select(wrapper).element.selectedIndex).toBe(-1)
    expect(wrapper.text()).toContain('Este workflow não tem status inicial')
    wrapper.unmount()
  })
})
