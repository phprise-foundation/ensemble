import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { toast } from '../src/store/toast.js'

describe('toast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    toast.toasts.splice(0)
  })

  afterEach(() => {
    toast.toasts.splice(0)
    vi.useRealTimers()
  })

  it('empilha no máximo três e remove o mais antigo', () => {
    toast.success('um')
    toast.success('dois')
    toast.success('três')
    toast.success('quatro')

    expect(toast.toasts).to.have.length(3)
    expect(toast.toasts.map((item) => item.text)).to.deep.equal(['dois', 'três', 'quatro'])
  })

  it('expira sucesso e informação em 4s', () => {
    toast.success('ok')
    toast.info('info')
    vi.advanceTimersByTime(3999)
    expect(toast.toasts).to.have.length(2)
    vi.advanceTimersByTime(1)
    expect(toast.toasts).to.have.length(0)
  })

  it('expira erro e aviso em 6s', () => {
    toast.error('falhou')
    toast.warning('atenção')
    vi.advanceTimersByTime(5999)
    expect(toast.toasts).to.have.length(2)
    vi.advanceTimersByTime(1)
    expect(toast.toasts).to.have.length(0)
  })

  it('dismiss remove pelo id', () => {
    toast.success('um')
    toast.error('dois')
    const first = toast.toasts[0]
    toast.dismiss(first.id)
    expect(toast.toasts.map((item) => item.text)).to.deep.equal(['dois'])
  })

  it('mantém o tipo de cada toast', () => {
    toast.success('s')
    toast.error('e')
    toast.info('i')
    expect(toast.toasts.map((item) => item.kind)).to.deep.equal(['success', 'error', 'info'])
  })
})
