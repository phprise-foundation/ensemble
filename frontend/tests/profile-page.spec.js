import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfilePage from '../src/views/ProfilePage.vue'
import { session } from '../src/store/session.js'
import { toast } from '../src/store/toast.js'

const { fetchUser } = vi.hoisted(() => ({ fetchUser: vi.fn() }))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
  onBeforeRouteLeave: vi.fn(),
  onBeforeRouteUpdate: vi.fn(),
}))

vi.mock('../src/api/index.js', () => ({
  api: {
    fetchUser,
    updateUser: vi.fn(),
  },
}))

function setFiles(element, files) {
  Object.defineProperty(element, 'files', { configurable: true, value: files })
}

function ultimoToast() {
  return toast.toasts[toast.toasts.length - 1]
}

describe('ProfilePage', () => {
  beforeEach(() => {
    session.login = 'admin'
    session.displayName = ''
    session.signIn()
    fetchUser.mockReset()
    fetchUser.mockResolvedValue({ login: 'admin', displayName: 'Maria Fernanda de Souza Oliveira' })
    toast.toasts.splice(0, toast.toasts.length)
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

  it('mostra só o hint estático do input de arquivo, sem hook de erro morto', async () => {
    await session.load()
    const wrapper = mount(ProfilePage)

    expect(wrapper.find('[data-test="file-error"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('PNG, JPEG or WebP up to 2 MB.')
    expect(wrapper.find('.hint.error').exists()).toBe(false)

    wrapper.unmount()
  })

  it('rejeita arquivo inválido com toast de erro, sem diálogo nativo', async () => {
    const alerta = vi.spyOn(window, 'alert').mockImplementation(() => {})
    const confirmacao = vi.spyOn(window, 'confirm').mockImplementation(() => true)
    await session.load()
    const wrapper = mount(ProfilePage)

    const input = wrapper.find('[data-test="photo-input"]')
    setFiles(input.element, [{ name: 'nota.txt', type: 'text/plain', size: 10 }])
    await input.trigger('change')
    await nextTick()

    const aviso = ultimoToast()
    expect(aviso?.kind).toBe('error')
    expect(aviso?.text).toMatch(/PNG|JPEG|WebP/)
    expect(alerta).not.toHaveBeenCalled()
    expect(confirmacao).not.toHaveBeenCalled()

    wrapper.unmount()
    alerta.mockRestore()
    confirmacao.mockRestore()
  })
})
