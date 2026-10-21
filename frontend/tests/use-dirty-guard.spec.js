import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { useDirtyGuard } from '../src/composables/useDirtyGuard.js'

const { pushRoute, routeLeaveHandlers, routeUpdateHandlers, confirmDiscard } = vi.hoisted(() => ({
  pushRoute: vi.fn(),
  routeLeaveHandlers: [],
  routeUpdateHandlers: [],
  confirmDiscard: vi.fn(),
}))

vi.mock('vue-router', () => ({
  onBeforeRouteLeave: (handler) => routeLeaveHandlers.push(handler),
  onBeforeRouteUpdate: (handler) => routeUpdateHandlers.push(handler),
}))

vi.mock('../src/store/dialog.js', () => ({
  dialog: { confirm: confirmDiscard },
}))

function mountGuard({ dirty = () => true, confirmLeave } = {}) {
  let guard
  const options = { router: { push: pushRoute }, isDirty: dirty }
  if (confirmLeave) options.confirmLeave = confirmLeave
  const Host = defineComponent({
    setup() {
      guard = useDirtyGuard(options)
      return () => h('div')
    },
  })
  mount(Host)
  return guard
}

const destino = { fullPath: '/board' }
const falha = { type: 4, text: 'aborted downstream' }

beforeEach(() => {
  routeLeaveHandlers.length = 0
  routeUpdateHandlers.length = 0
  pushRoute.mockReset()
  confirmDiscard.mockReset()
})

describe('useDirtyGuard', () => {
  it('arma a flag one-shot e reempurra o destino sem segunda captura ao confirmar', async () => {
    confirmDiscard.mockResolvedValue(true)
    pushRoute.mockResolvedValue(undefined)
    const guard = mountGuard()

    const primeira = await routeLeaveHandlers[0](destino)
    expect(primeira).toBe(false)
    expect(confirmDiscard).toHaveBeenCalledTimes(1)
    expect(guard.pendingTo.value).toBe('/board')

    const segunda = await routeLeaveHandlers[0](destino)
    expect(segunda).toBe(true)
    expect(confirmDiscard).toHaveBeenCalledTimes(1)
    expect(pushRoute).toHaveBeenCalledTimes(1)
    expect(pushRoute).toHaveBeenCalledWith('/board')
    expect(guard.allowNext.value).toBe(false)
    expect(guard.pendingTo.value).toBe(null)
  })

  it('preserva o rascunho ao cancelar e pergunta de novo na próxima navegação', async () => {
    confirmDiscard.mockResolvedValue(false)
    const guard = mountGuard()

    expect(await routeLeaveHandlers[0](destino)).toBe(false)
    expect(pushRoute).not.toHaveBeenCalled()
    expect(guard.pendingTo.value).toBe(null)
    expect(guard.allowNext.value).toBe(false)

    confirmDiscard.mockResolvedValue(true)
    pushRoute.mockResolvedValue(undefined)
    expect(await routeLeaveHandlers[0](destino)).toBe(false)
    expect(confirmDiscard).toHaveBeenCalledTimes(2)
    expect(pushRoute).toHaveBeenCalledWith('/board')

    expect(await routeLeaveHandlers[0](destino)).toBe(true)
  })

  it('consome a flag quando o router.push falha a jusante', async () => {
    confirmDiscard.mockResolvedValue(true)
    pushRoute.mockResolvedValue(falha)
    const guard = mountGuard()

    expect(await routeLeaveHandlers[0](destino)).toBe(false)
    expect(guard.allowNext.value).toBe(false)

    pushRoute.mockResolvedValue(undefined)
    expect(await routeLeaveHandlers[0](destino)).toBe(false)
    expect(pushRoute).toHaveBeenCalledTimes(2)
    expect(confirmDiscard).toHaveBeenCalledTimes(2)
  })

  it('não pergunta quando a página não está suja', async () => {
    const guard = mountGuard({ dirty: () => false })

    expect(await routeLeaveHandlers[0](destino)).toBe(true)
    expect(confirmDiscard).not.toHaveBeenCalled()
    expect(pushRoute).not.toHaveBeenCalled()
    expect(guard.allowNext.value).toBe(false)
  })

  it('usa o confirmLeave injetado em vez do diálogo padrão', async () => {
    const confirmLeave = vi.fn().mockResolvedValue(true)
    pushRoute.mockResolvedValue(undefined)
    mountGuard({ confirmLeave })

    await routeLeaveHandlers[0](destino)

    expect(confirmLeave).toHaveBeenCalledTimes(1)
    expect(confirmDiscard).not.toHaveBeenCalled()
  })

  it('captura também navegação in-page pelo onBeforeRouteUpdate', async () => {
    confirmDiscard.mockResolvedValue(true)
    pushRoute.mockResolvedValue(undefined)
    mountGuard()

    const saida = routeLeaveHandlers[0](destino)
    const update = routeUpdateHandlers[0](destino)
    await Promise.all([saida, update])
    expect(pushRoute).toHaveBeenCalledWith('/board')
  })

  it('usa o diálogo de descarte perigoso por padrão', async () => {
    confirmDiscard.mockResolvedValue(false)
    mountGuard()

    await routeLeaveHandlers[0](destino)

    expect(confirmDiscard).toHaveBeenCalledWith({
      title: 'Discard changes?',
      message: 'Your unsaved changes will be lost.',
      danger: true,
    })
  })
})
