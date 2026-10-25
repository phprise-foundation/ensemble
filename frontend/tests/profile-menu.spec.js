import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfileMenu from '../src/components/ProfileMenu.vue'
import { session } from '../src/store/session.js'

const FOTO = 'data:image/webp;base64,Zm90by1kby11c3Vhcmlv'

const client = vi.hoisted(() => ({ tokenValue: 't', request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  token: () => client.tokenValue,
  setToken: (value) => {
    client.tokenValue = value
  },
  request: (...args) => client.request(...args),
}))


vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
}))

describe('ProfileMenu', () => {
  async function hydrate({ avatar = '', color = '#e5484d' } = {}) {
    client.request.mockImplementation(async (method, path) => {
      if (path === '/api/me') {
        return { user: { login: 'admin', displayName: 'Admin', avatar, avatarColor: color } }
      }
      if (path === '/api/workspaces') {
        return { workspaces: [{ id: 'ws', name: 'Workspace' }] }
      }
      throw new Error(`unexpected ${method} ${path}`)
    })
    await session.load()
  }

  beforeEach(() => {
    client.tokenValue = 't'
    client.request = vi.fn()
  })

  it('mostra a foto do usuário no Avatar do topbar quando ela existe', async () => {
    await hydrate({ avatar: FOTO })
    const wrapper = mount(ProfileMenu)

    const imagem = wrapper.find('.profile-avatar img')
    expect(imagem.exists()).toBe(true)
    expect(imagem.attributes('src')).toBe(FOTO)

    wrapper.unmount()
  })

  it('cai para a inicial sobre a cor do usuário quando não há foto', async () => {
    await hydrate()
    const wrapper = mount(ProfileMenu)
    const avatar = wrapper.find('.profile-avatar')

    expect(avatar.find('img').exists()).toBe(false)
    expect(avatar.text()).toBe('A')
    expect(avatar.attributes('style')).toContain('background-color: rgb(229, 72, 77)')

    wrapper.unmount()
  })

  it('acompanha a sessão quando a foto hidrata depois da montagem', async () => {
    await hydrate()
    const wrapper = mount(ProfileMenu)
    expect(wrapper.find('.profile-avatar img').exists()).toBe(false)

    await hydrate({ avatar: FOTO })
    await nextTick()

    expect(wrapper.find('.profile-avatar img').attributes('src')).toBe(FOTO)

    wrapper.unmount()
  })
})
