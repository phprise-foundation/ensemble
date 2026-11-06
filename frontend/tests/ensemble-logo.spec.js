import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import EnsembleLogo from '../src/components/EnsembleLogo.vue'

describe('EnsembleLogo', () => {
  it('uses the bluesmoke mark by default', () => {
    const wrapper = mount(EnsembleLogo)
    const image = wrapper.find('img')

    expect(image.attributes('src')).toContain('ens-logo-bluesmoke')
    expect(image.attributes('alt')).toBe('Ensemble')
    expect(image.classes()).toContain('ensemble-logo--mark')
  })

  it('switches between the black, white and bluesmoke marks', () => {
    expect(mount(EnsembleLogo, { props: { tone: 'white' } }).find('img').attributes('src'))
      .toContain('ens-logo-white')
    expect(mount(EnsembleLogo, { props: { tone: 'black' } }).find('img').attributes('src'))
      .toContain('ens-logo-black')
  })

  it('falls back to bluesmoke for an unknown tone', () => {
    expect(mount(EnsembleLogo, { props: { tone: 'unknown' } }).find('img').attributes('src'))
      .toContain('ens-logo-bluesmoke')
  })
})
