import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CrudPage from '../src/components/CrudPage.vue'

const schema = {
  collection: 'roles',
  singular: 'role',
  title: 'Roles',
  items: () => [
    {
      id: '1',
      name: 'Tech Lead',
      description: '## Review\n\n**Approves** changes and merges [PRs](https://example.com).',
      unblock: true,
      review: true,
      implement: false,
    },
    {
      id: '2',
      name: 'Coder',
      description: 'Writes code.',
      unblock: false,
      review: false,
      implement: true,
    },
  ],
  columns: [
    { key: 'name', label: 'Name' },
    { key: 'description', label: 'Description' },
    { key: 'unblock', label: 'Unblock' },
    { key: 'review', label: 'Reviews' },
    { key: 'implement', label: 'Implements' },
  ],
  fields: [
    { key: 'name', label: 'Name', type: 'text', required: true },
    { key: 'description', label: 'Description', type: 'textarea' },
    { key: 'unblock', label: 'Unblock', type: 'checkbox' },
    { key: 'review', label: 'Reviews', type: 'checkbox' },
    { key: 'implement', label: 'Implements', type: 'checkbox' },
  ],
  defaults: () => ({ name: '', description: '', unblock: false, review: false, implement: false }),
}

describe('CrudPage cards', () => {
  it('renders one card per item with a plain, truncated description', () => {
    const wrapper = mount(CrudPage, { props: { schema } })
    const cards = wrapper.findAll('.crud-card')
    expect(cards).toHaveLength(2)
    expect(cards[0].find('.crud-card-desc').text()).toBe('Review Approves changes and merges PRs.')
    expect(cards[0].text()).not.toContain('**')
    expect(cards[0].text()).not.toContain('##')
  })

  it('shows only the enabled boolean flags as chips', () => {
    const wrapper = mount(CrudPage, { props: { schema } })
    const first = wrapper.findAll('.crud-card')[0]
    expect(first.findAll('.chip').map((chip) => chip.text())).toEqual(['Unblock', 'Reviews'])
    const second = wrapper.findAll('.crud-card')[1]
    expect(second.findAll('.chip').map((chip) => chip.text())).toEqual(['Implements'])
  })

  it('uses the switch component inside the editor', async () => {
    const wrapper = mount(CrudPage, { props: { schema } })
    await wrapper.findAll('.crud-card')[0].find('button[title="Edit"]').trigger('click')
    expect(wrapper.find('.modal-card .switch input').exists()).toBe(true)
    expect(wrapper.find('.modal-card input[type="checkbox"]').attributes('role')).toBe('switch')
  })

  it('keeps the drag hook for reorderable collections', () => {
    const reorderable = { ...schema, reorder: async () => {} }
    const wrapper = mount(CrudPage, { props: { schema: reorderable } })
    expect(wrapper.findAll('.crud-card')[0].attributes('draggable')).toBe('true')
  })
})
