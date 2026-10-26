import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

const { state, updateIssue, addComment, boardStore } = await vi.hoisted(async () => {
  const { reactive, computed } = await import('vue')
  const updateIssue = vi.fn().mockResolvedValue(null)
  const addComment = vi.fn().mockResolvedValue(null)
  const state = reactive({
    issues: [],
    selectedIssueId: null,
    agents: [],
    statuses: [],
    boardId: null,
  })
  const boardStore = {
    state,
    selectedIssue: computed(
      () => state.issues.find((issue) => issue.id === state.selectedIssueId) ?? null,
    ),
    status: () => ({ name: 'Coding', type: 'active' }),
    issueType: () => ({ fields: [] }),
    agent: () => null,
    role: () => null,
    userTargets: () => [],
    isOverdue: () => false,
    simulated: () => false,
    clearSelection: () => {
      state.selectedIssueId = null
    },
    updateIssue,
    addComment,
  }
  return { state, updateIssue, addComment, boardStore }
})

vi.mock('../src/store/board.js', () => ({ boardStore }))
vi.mock('../src/api/index.js', () => ({ api: { updateIssue, addComment } }))

import IssuePanel from '../src/components/IssuePanel.vue'

const buildIssue = (overrides = {}) => ({
  id: 'KAN-1',
  summary: 'Issue limites',
  statusId: 's1',
  assigneeId: null,
  description: 'Original description',
  comments: [],
  updatedAt: '2026-01-01T00:00:00.000Z',
  ...overrides,
})

const select = (issue) => {
  state.issues = [issue]
  state.selectedIssueId = issue.id
}

const mountPanel = () =>
  mount(IssuePanel, {
    global: { stubs: { AppIcon: true } },
  })

const buttonByText = (wrapper, label) =>
  wrapper.findAll('button').find((button) => button.text() === label)

const editDescription = async (wrapper, value) => {
  await wrapper.find('[aria-label="Edit description"]').trigger('click')
  await wrapper.find('textarea[aria-label="Description"]').setValue(value)
}

describe('IssuePanel limites', () => {
  it('Edit abre com a descrição atual', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    await wrapper.find('[aria-label="Edit description"]').trigger('click')

    const textarea = wrapper.find('textarea[aria-label="Description"]')
    expect(textarea.exists()).toBe(true)
    expect(textarea.element.value).toBe('Original description')
    expect(wrapper.find('#issue-description-limit .field-limit-count').text()).toBe('20/4095')
  })

  it('acima do limite mostra erro e desabilita Save', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    await editDescription(wrapper, 'a'.repeat(4096))

    expect(wrapper.find('#issue-description-limit .field-limit-error').text()).toContain('4095')
    expect(buttonByText(wrapper, 'Save').attributes('disabled')).toBeDefined()
  })

  it('Cancel descarta o rascunho', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    await editDescription(wrapper, 'a'.repeat(4096))
    await buttonByText(wrapper, 'Cancel').trigger('click')

    expect(wrapper.find('textarea[aria-label="Description"]').exists()).toBe(false)
    expect(wrapper.find('.body').text()).toBe('Original description')
  })

  it('salvar abaixo do limite chama updateIssue com o texto novo', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    await editDescription(wrapper, 'New text')
    await buttonByText(wrapper, 'Save').trigger('click')

    expect(updateIssue).toHaveBeenCalledWith('KAN-1', { description: 'New text' })
    expect(wrapper.find('textarea[aria-label="Description"]').exists()).toBe(false)
  })

  it('comentário acima do limite não chama addComment', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    const textarea = wrapper.find('textarea[aria-label="Comment"]')
    expect(wrapper.find('#issue-comment-limit .field-limit-error').text()).toBe('')
    await textarea.setValue('a'.repeat(1024))
    await buttonByText(wrapper, 'Comment').trigger('click')

    expect(buttonByText(wrapper, 'Comment').attributes('disabled')).toBeDefined()
    expect(wrapper.find('#issue-comment-limit .field-limit-error').text()).toContain('1023')
    expect(addComment).not.toHaveBeenCalled()
  })

  it('comentário válido é enviado com trim()', async () => {
    select(buildIssue())
    const wrapper = mountPanel()

    await wrapper.find('textarea[aria-label="Comment"]').setValue('  Valid text  ')
    await buttonByText(wrapper, 'Comment').trigger('click')

    expect(addComment).toHaveBeenCalledWith('KAN-1', 'Valid text')
  })

  it('trocar de issue sai do modo de edição', async () => {
    select(buildIssue())
    const wrapper = mountPanel()
    await wrapper.find('[aria-label="Edit description"]').trigger('click')
    expect(wrapper.find('textarea[aria-label="Description"]').exists()).toBe(true)

    const other = buildIssue({ id: 'KAN-2', summary: 'Issue Doutro', description: 'Another issue' })
    state.issues = [buildIssue(), other]
    state.selectedIssueId = other.id
    await nextTick()

    expect(wrapper.find('textarea[aria-label="Description"]').exists()).toBe(false)
    expect(wrapper.find('.body').text()).toBe('Another issue')
  })
})
