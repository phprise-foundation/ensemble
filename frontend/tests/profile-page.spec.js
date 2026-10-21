import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import ProfilePage from '../src/views/ProfilePage.vue'
import { session } from '../src/store/session.js'
import { normalizeImageFile } from '../src/lib/image-normalize.js'

const { fetchUser, updateUser } = vi.hoisted(() => ({
  fetchUser: vi.fn(),
  updateUser: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn(), back: vi.fn() }),
}))

vi.mock('../src/api/index.js', () => ({
  api: {
    fetchUser,
    updateUser,
  },
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

async function mountPage(overrides = {}) {
  fetchUser.mockResolvedValue(user(overrides))
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
    session.login = 'admin'
    session.displayName = ''
    session.signIn()
    fetchUser.mockReset()
    updateUser.mockReset()
    normalizeImageFile.mockReset()
    fetchUser.mockResolvedValue(user({ displayName: 'Maria Fernanda de Souza Oliveira', avatar: null }))
    updateUser.mockResolvedValue(user({ displayName: 'Maria Silva' }))
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
    expect(updateUser).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('mostra loading no Save e o status de sucesso depois do PATCH', async () => {
    let resolveUpdate
    updateUser.mockImplementation(
      () =>
        new Promise((resolve) => {
          resolveUpdate = resolve
        }),
    )
    const wrapper = await mountPage()

    await wrapper.find('[data-test="display-name"]').setValue('Maria Silva')
    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeUndefined()
    await wrapper.find('form').trigger('submit')
    await nextTick()

    expect(wrapper.text()).toContain('Saving')
    expect(wrapper.find('[data-test="save"]').attributes('disabled')).toBeDefined()

    resolveUpdate(user({ displayName: 'Maria Silva' }))
    await flushPromises()

    expect(wrapper.find('[data-test="save-status"]').text()).toBe('Saved.')
    expect(updateUser).toHaveBeenCalledWith({ displayName: 'Maria Silva' })

    wrapper.unmount()
  })

  it('mostra o erro inline custom quando o PATCH falha', async () => {
    updateUser.mockRejectedValue(new Error('network down'))
    const wrapper = await mountPage()

    await wrapper.find('[data-test="display-name"]').setValue('Maria Silva')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('.hint.error').text()).toBe('network down')
    expect(wrapper.find('[data-test="save-status"]').exists()).toBe(false)

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
    expect(updateUser).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('Remove marca null no rascunho e só grava no Save', async () => {
    const wrapper = await mountPage({ avatar: 'data:image/png;base64,ZZZ' })

    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)
    await wrapper.find('[data-test="remove-photo"]').trigger('click')

    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(false)
    expect(wrapper.find('[data-test="avatar-preview"]').text()).toBe('M')
    expect(updateUser).not.toHaveBeenCalled()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateUser).toHaveBeenCalledWith({ displayName: 'Maria', avatar: null })

    wrapper.unmount()
  })

  it('rejeita tipo inválido e arquivo acima de 2 MB sem alert e preserva a foto', async () => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {})
    const wrapper = await mountPage()

    await chooseFile(wrapper, new File(['photo'], 'photo.png', { type: 'image/png' }))
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)

    await chooseFile(wrapper, new File(['text'], 'notes.txt', { type: 'text/plain' }))
    expect(wrapper.find('.hint.error').text()).toBe('Use a PNG, JPEG or WebP image.')
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)

    await chooseFile(
      wrapper,
      new File([new Uint8Array(2 * 1024 * 1024 + 1)], 'big.png', { type: 'image/png' }),
    )
    expect(wrapper.find('.hint.error').text()).toBe('The image must be at most 2 MB.')
    expect(wrapper.find('[data-test="avatar-preview"] img').exists()).toBe(true)
    expect(alert).not.toHaveBeenCalled()
    expect(normalizeImageFile).toHaveBeenCalledTimes(1)

    wrapper.unmount()
  })
})
