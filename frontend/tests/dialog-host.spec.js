import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import DialogHost from '../src/components/DialogHost.vue'
import { dialog } from '../src/store/dialog.js'

let trigger = null
let wrapper = null
let app = null

const flush = async () => {
  await nextTick()
  await nextTick()
}

const openConfirm = async (options = {}) => {
  const result = dialog.confirm({ title: 'Delete?', message: 'Will be removed.', danger: true, ...options })
  await flush()
  return { result }
}

const click = (selector) => {
  const element = document.querySelector(selector)
  expect(element, selector).to.exist
  element.click()
}

describe('DialogHost', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
    app = document.createElement('div')
    app.id = 'app'
    trigger = document.createElement('button')
    trigger.textContent = 'Abrir'
    app.append(trigger)
    document.body.append(app)
    trigger.focus()
    wrapper = mount(DialogHost, { attachTo: document.body })
  })

  afterEach(() => {
    if (wrapper) wrapper.unmount()
    wrapper = null
    if (dialog.open) dialog.finish(false)
    vi.restoreAllMocks()
  })

  it('abre com foco no cancelar, ARIA de alertdialog e scroll lock', async () => {
    await openConfirm()
    const card = document.querySelector('.dialog-card')
    expect(card.getAttribute('role')).toBe('alertdialog')
    expect(card.getAttribute('aria-modal')).toBe('true')
    expect(card.getAttribute('aria-labelledby')).toBe('dialog-title')
    expect(card.getAttribute('aria-describedby')).toBe('dialog-message')
    expect(document.activeElement).toBe(document.querySelector('.dialog-cancel'))
    expect(document.body.style.overflow).toBe('hidden')
    expect(app.inert).toBe(true)
  })

  it('resolve false ao clicar em cancelar e devolve o foco ao gatilho', async () => {
    const { result } = await openConfirm()
    click('.dialog-cancel')
    await flush()
    await expect(result).resolves.toBe(false)
    expect(dialog.open).toBe(false)
    expect(document.activeElement).toBe(trigger)
    expect(document.body.style.overflow).toBe('')
    expect(app.inert).toBe(false)
  })

  it('resolve false com Escape', async () => {
    const { result } = await openConfirm()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flush()
    await expect(result).resolves.toBe(false)
    expect(dialog.open).toBe(false)
  })

  it('resolve true com Enter fora de um botão', async () => {
    const { result } = await openConfirm()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
    await flush()
    await expect(result).resolves.toBe(true)
  })

  it('resolve true no clique do botão de confirmação', async () => {
    const { result } = await openConfirm()
    click('.dialog-confirm')
    await flush()
    await expect(result).resolves.toBe(true)
  })

  it('ignora o scrim quando scrimCloses é false e fecha no alert', async () => {
    const { result } = await openConfirm({ scrimCloses: false })
    click('.dialog-overlay')
    await flush()
    expect(dialog.open).toBe(true)
    dialog.finish(false)
    await expect(result).resolves.toBe(false)

    const alert = dialog.alert({ title: 'Aviso', message: 'Tudo certo' })
    await flush()
    expect(document.querySelector('.dialog-cancel')).to.be.null
    expect(document.querySelector('.dialog-confirm').textContent).toBe('OK')
    click('.dialog-overlay')
    await flush()
    await expect(alert).resolves.toBe(false)
  })

  it('permite uma caixa por vez', async () => {
    const { result: first } = await openConfirm()
    await expect(dialog.confirm({ title: 'Outra' })).resolves.toBe(false)
    expect(document.querySelectorAll('.dialog-card')).to.have.length(1)
    dialog.finish(false)
    await expect(first).resolves.toBe(false)
  })

  it('prende o foco entre os botões com Tab', async () => {
    await openConfirm()
    const cancel = document.querySelector('.dialog-cancel')
    const confirm = document.querySelector('.dialog-confirm')
    confirm.focus()
    const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true })
    confirm.dispatchEvent(tab)
    expect(document.activeElement).toBe(cancel)
  })
})
