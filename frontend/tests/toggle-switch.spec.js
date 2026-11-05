import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ToggleSwitch from '../src/components/ToggleSwitch.vue'

describe('ToggleSwitch', () => {
  it('renders an accessible switch with the current state', () => {
    const wrapper = mount(ToggleSwitch, { props: { modelValue: true, label: 'Active' } })
    const input = wrapper.find('input')
    expect(input.attributes('type')).toBe('checkbox')
    expect(input.attributes('role')).toBe('switch')
    expect(input.attributes('aria-checked')).toBe('true')
    expect(input.attributes('aria-label')).toBe('Active')
    expect(input.element.checked).toBe(true)
  })

  it('emits the new value on change', async () => {
    const wrapper = mount(ToggleSwitch, { props: { modelValue: false, label: 'Active' } })
    await wrapper.find('input').setValue(true)
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([true])
  })

  it('disables the input when requested', () => {
    const wrapper = mount(ToggleSwitch, { props: { modelValue: false, disabled: true } })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
  })
})
