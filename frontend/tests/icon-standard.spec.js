import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AppIcon from '../src/components/AppIcon.vue'

const MAPPING = {
  board: 'lucide-kanban-icon',
  workspace: 'lucide-app-window-icon',
  folder: 'lucide-folder-icon',
  layers: 'lucide-layers-icon',
  agents: 'lucide-users-icon',
  roles: 'lucide-shield-icon',
  profiles: 'lucide-star-icon',
  statuses: 'lucide-clock-icon',
  transitions: 'lucide-arrow-right-icon',
  issues: 'lucide-file-text-icon',
  experiences: 'lucide-bookmark-icon',
  plus: 'lucide-plus-icon',
  chevron: 'lucide-chevron-right-icon',
  sun: 'lucide-sun-icon',
  moon: 'lucide-moon-icon',
  close: 'lucide-x-icon',
  delete: 'lucide-trash2-icon',
  edit: 'lucide-pencil-icon',
  check: 'lucide-check-icon',
  menu: 'lucide-menu-icon',
  comment: 'lucide-message-square-icon',
  warning: 'lucide-triangle-alert-icon',
  sidebar: 'lucide-panel-left-icon',
  copilot: 'lucide-panel-right-icon',
  console: 'lucide-square-terminal-icon',
}

describe('AppIcon', () => {
  it.each(Object.keys(MAPPING))(
    'renderiza %s como Lucide com traço 2, aria-hidden e currentColor',
    (name) => {
      const wrapper = mount(AppIcon, { props: { name, size: 16 } })
      expect(wrapper.element.tagName.toLowerCase()).toBe('svg')
      expect(wrapper.classes()).toContain(MAPPING[name])
      expect(wrapper.attributes('width')).toBe('16')
      expect(wrapper.attributes('height')).toBe('16')
      expect(wrapper.attributes('stroke-width')).toBe('2')
      expect(wrapper.attributes('aria-hidden')).toBe('true')
      expect(wrapper.attributes('stroke')).toBe('currentColor')
      expect(wrapper.attributes('fill')).toBe('none')
    },
  )

  it.each(Object.keys(MAPPING))('%s cai no svg raiz com fallthrough de class', (name) => {
    const wrapper = mount(AppIcon, { props: { name }, attrs: { class: 'extra' } })
    expect(wrapper.classes()).toContain('extra')
  })

  it('default de size é 18', () => {
    const wrapper = mount(AppIcon, { props: { name: 'check' } })
    expect(wrapper.attributes('width')).toBe('18')
  })

  it('smartboard não vira Lucide e mantém o paths manual', () => {
    const wrapper = mount(AppIcon, { props: { name: 'smartboard', size: 18 } })
    expect(wrapper.classes()).not.toContain('lucide-smartboard-icon')
    expect(wrapper.attributes('viewBox')).toBe('0 0 24 24')
    expect(wrapper.findAll('path')).toHaveLength(4)
  })
})
