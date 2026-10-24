import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfilePage from '../src/views/ProfilePage.vue'
import { session } from '../src/store/session.js'
import { toast } from '../src/store/toast.js'
import { normalizeImageFile } from '../src/lib/image-normalize.js'

const client = vi.hoisted(() => ({ tokenValue: 't', request: vi.fn() }))

vi.mock('../src/api/client.js', () => ({
  token: () => client.tokenValue,
  setToken: (value) => {
    client.tokenValue = value
  },
  request: (...args) => client.request(...args),
}))


vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
  onBeforeRouteLeave: vi.fn(),
  onBeforeRouteUpdate: vi.fn(),
}))



vi.mock('../src/lib/image-normalize.js', () => ({
  normalizeImageFile: vi.fn(),
}))

const user = (overrides = {}) => ({
  login: 'admin',
  displayName: 'Maria',
  avatar: null,
  avatarColor: '#4c9aff',
  ...overrides,
})

function ultimoToast() {
  return toast.toasts[toast.toasts.length - 1]
}

const handlers = {
  patch: () => ({ user: user({ displayName: 'Maria Silva' }) }),
}

function patchCalls() {
  return client.request.mock.calls.filter(([method, path]) => method === 'PATCH' && path === '/api/me')
}

function mockSession(userPayload) {
  client.request.mockImplementation(async (method, path) => {
    if (method === 'PATCH' && path === '/api/me') {
      return handlers.patch()
    }
    if (path === '/api/me') {
      return { user: userPayload }
    }
    if (path === '/api/workspaces') {
      return { workspaces: [{ id: 'ws', name: 'Workspace' }] }
    }
    throw new Error(`unexpected ${method} ${path}`)
  })
}

async function mountPage(overrides = {}) {
  mockSession(user(overrides))
  await session.load()
  await nextTick()
  return mount(ProfilePage)
}

async function chooseFile(wrapper, file) {
  const input = wrapper.find('[data-test="photo-input"]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

describe('ProfilePage', () => {
  beforeEach(() => {
    client.tokenValue = 't'
    client.request = vi.fn()
    session.login = 'admin'
    session.displayName = ''
    normalizeImageFile.mockReset()
    toast.toasts.splice(0, toast.toasts.length)
    handlers.patch = () => ({ user: user({ displayName: 'Maria Silva' }) })
    mockSession(user({ displayName: 'Maria Fernanda de Souza Oliveira', avatar: null }))
    normalizeImageFile.mockResolvedValue('data:image/webp;base64,AAA')
  })

  afterEach(() => {
    vi.restoreAllMocks()
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

  it('mantém o Save disabled sem alterações e o login readonly', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeDefined()
    expect(wrapper.find('#profile-login').element.readOnly).toBe(true)
    expect(patchCalls()).toHaveLength(0)

    wrapper.unmount()
  })

  it('mostra loading no Save e confirma o sucesso por toast depois do PATCH', async () => {
    let resolveUpdate
    handlers.patch = () =>
      new Promise((resolve) => {
        resolveUpdate = resolve
      })
    const wrapper = await mountPage()

    await wrapper.find('[data-test="display-name"]').setValue('Maria Silva')
    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeUndefined()
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.text()).toContain('Saving')
    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeDefined()

    resolveUpdate({ user: user({ displayName: 'Maria Silva' }) })
    await flushPromises()

    expect(ultimoToast()).toMatchObject({ kind: 'success', text: 'Saved.' })
    expect(patchCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('avisa o erro do PATCH por toast, sem diálogo nativo', async () => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {})
    handlers.patch = () => {
      throw new Error('network down')
    }
    const wrapper = await mountPage()

    await wrapper.find('[data-test="display-name"]').setValue('Maria Silva')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(ultimoToast()).toMatchObject({ kind: 'error', text: 'network down' })
    expect(alert).not.toHaveBeenCalled()
    expect(patchCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('mostra só o hint estático do input de arquivo, sem hook de erro morto', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-test="file-error"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('PNG, JPEG or WebP up to 2 MB.')
    expect(wrapper.find('.hint.error').exists()).toBe(false)

    wrapper.unmount()
  })

  it('pré-visualiza a foto em rascunho sem gravar antes do Save', async () => {
    const wrapper = await mountPage()

    expect(wrapper.find('[data-test="avatar-preview"]').text()).toBe('M')
    await chooseFile(wrapper, new File(['photo'], 'photo.png', { type: 'image/png' }))

    expect(wrapper.find('[data-test="avatar-preview"] img').attributes('src')).toBe(
      'data:image/webp;base64,AAA',
    )
    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeUndefined()
    expect(patchCalls()).toHaveLength(0)

    wrapper.unmount()
  })

  it('Remove marca null no rascunho e só grava no Save', async () => {
    const wrapper = await mountPage({ avatar: 'data:image/png;base64,ZZZ' })

    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)
    await wrapper.find('[data-test="remove-photo"]').trigger('click')

    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(false)
    expect(wrapper.find('[data-test="avatar-preview"]').text()).toBe('M')
    expect(patchCalls()).toHaveLength(0)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(patchCalls()).toHaveLength(1)

    wrapper.unmount()
  })

  it('rejeita tipo inválido e arquivo acima de 2 MB por toast e preserva a foto', async () => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {})
    const wrapper = await mountPage()

    await chooseFile(wrapper, new File(['photo'], 'photo.png', { type: 'image/png' }))
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)

    await chooseFile(wrapper, new File(['text'], 'notes.txt', { type: 'text/plain' }))
    expect(ultimoToast()).toMatchObject({ kind: 'error', text: 'Use a PNG, JPEG or WebP image.' })
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)

    await chooseFile(
      wrapper,
      new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'big.png', { type: 'image/png' }),
    )
    expect(ultimoToast()).toMatchObject({ kind: 'error', text: 'The image must be at most 2 MB.' })
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)
    expect(alert).not.toHaveBeenCalled()
    expect(normalizeImageFile).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })
})
