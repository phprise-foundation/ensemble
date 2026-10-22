import { describe, it, expect } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import Avatar from '../src/components/Avatar.vue'

const IMAGE = 'data:image/png;base64,Zm90by1ncmFuZGU='
const OTHER = 'data:image/png;base64,b3V0cmEtZm90bw=='

function channels(style, property) {
  const match = new RegExp(`(?:^|;)\\s*${property}:\\s*rgb\\((\\d+),\\s*(\\d+),\\s*(\\d+)\\)`).exec(style)
  if (!match) throw new Error(`missing ${property} in ${style}`)
  return match.slice(1).map(Number)
}

function channel(value) {
  const normalized = value / 255
  return normalized <= 0.03928
    ? normalized / 12.92
    : ((normalized + 0.055) / 1.055) ** 2.4
}

function luminance(rgb) {
  const [r, g, b] = rgb.map(channel)
  return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

function contrast(foreground, background) {
  const first = luminance(foreground)
  const second = luminance(background)
  const lighter = Math.max(first, second)
  const darker = Math.min(first, second)
  return (lighter + 0.05) / (darker + 0.05)
}

describe('Avatar', () => {
  it('aplica as classes de tamanho e o anel', () => {
    const plain = mount(Avatar, { props: { name: 'Vivy' } })
    expect(plain.classes()).not.toContain('ring')
    plain.unmount()

    for (const size of ['sm', 'md', 'lg']) {
      const wrapper = mount(Avatar, { props: { name: 'Vivy', size, ring: true } })
      expect(wrapper.classes()).toContain('avatar')
      expect(wrapper.classes()).toContain(size)
      expect(wrapper.classes()).toContain('ring')
      wrapper.unmount()
    }
  })

  it('renderiza a imagem informada', () => {
    const wrapper = mount(Avatar, { props: { name: 'Vivy', image: IMAGE } })
    const image = wrapper.find('img')
    expect(image.exists()).toBe(true)
    expect(image.attributes('src')).toBe(IMAGE)
    expect(image.attributes('alt')).toBe('')
    wrapper.unmount()
  })

  it('usa a inicial maiúscula e ? para nome vazio', () => {
    const vivy = mount(Avatar, { props: { name: 'vivy' } })
    expect(vivy.text()).toBe('V')
    vivy.unmount()

    const empty = mount(Avatar, { props: { name: '   ' } })
    expect(empty.text()).toBe('?')
    empty.unmount()
  })

  it('expõe ARIA quando informativo e esconde quando decorativo', () => {
    const informative = mount(Avatar, { props: { name: 'Vivy' } })
    expect(informative.attributes('role')).toBe('img')
    expect(informative.attributes('aria-label')).toBe('Avatar de Vivy')
    informative.unmount()

    const decorative = mount(Avatar, { props: { name: 'Vivy', decorative: true } })
    expect(decorative.attributes('role')).toBeUndefined()
    expect(decorative.attributes('aria-label')).toBeUndefined()
    expect(decorative.attributes('aria-hidden')).toBe('true')
    decorative.unmount()
  })

  it('cai no fallback quando a imagem falha e volta ao trocar a foto', async () => {
    const wrapper = mount(Avatar, { props: { name: 'Vivy', image: IMAGE } })
    expect(wrapper.find('img').exists()).toBe(true)

    await wrapper.find('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.text()).toBe('V')

    await wrapper.setProps({ image: OTHER })
    await nextTick()
    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.find('img').attributes('src')).toBe(OTHER)
    wrapper.unmount()
  })

  it('mantém o texto legível sobre cores claras, escuras e médias', () => {
    for (const color of ['#ffffff', '#000000', '#4c9aff', '#e5484d', '#8a7249', '#00b8d9']) {
      const wrapper = mount(Avatar, { props: { name: 'Vivy', color } })
      const style = wrapper.attributes('style')
      const ratio = contrast(channels(style, 'color'), channels(style, 'background-color'))
      expect(ratio, `contraste para ${color}`).to.be.at.least(4.5)
      wrapper.unmount()
    }
  })
})
