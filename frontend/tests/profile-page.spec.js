import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfilePage from '../src/views/ProfilePage.vue'
import { session } from '../src/store/session.js'

const { fetchUser } = vi.hoisted(() => ({ fetchUser: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
}))

vi.mock('../src/api/index.js', () => ({
  api: {
    fetchUser,
    updateUser: vi.fn(),
  },
}))

describe('ProfilePage', () => {
  beforeEach(() => {
    session.login = 'admin'
    session.displayName = ''
    session.signIn()
    fetchUser.mockReset()
    fetchUser.mockResolvedValue({ login: 'admin', displayName: 'Maria Fernanda de Souza Oliveira' })
  })

  it('hidrata o campo de nome quando a sessão resolve depois da montagem', async () => {
    const wrapper = mount(ProfilePage)
    const field = wrapper.find('#profile-name')

    expect(field.element.value).toBe('')

    await session.load()
    await nextTick()

    expect(field.element.value).toBe('Maria Fernanda de Souza Oliveira')
    wrapper.unmount()
  })
})
