import { describe, it, expect } from 'vitest'
import { DEFAULT_PANELS, PANELS_KEY, loadPanels, savePanels } from '../src/store/uiPanels.js'

function memoryStorage(initial = {}) {
  const data = new Map(Object.entries(initial))
  return {
    getItem: (key) => (data.has(key) ? data.get(key) : null),
    setItem: (key, value) => data.set(key, String(value)),
    dump: () => Object.fromEntries(data),
  }
}

describe('ui panels persistence', () => {
  it('defaults to every panel closed', () => {
    expect(DEFAULT_PANELS).toEqual({
      sidebarCollapsed: true,
      copilotOpen: false,
      consoleOpen: false,
      consoleHeight: 240,
    })
    expect(loadPanels(memoryStorage())).toEqual(DEFAULT_PANELS)
  })

  it('round-trips the user choices', () => {
    const storage = memoryStorage()
    savePanels({ sidebarCollapsed: false, copilotOpen: true, consoleOpen: true, consoleHeight: 300 }, storage)
    expect(loadPanels(storage)).toEqual({
      sidebarCollapsed: false,
      copilotOpen: true,
      consoleOpen: true,
      consoleHeight: 300,
    })
    expect(storage.dump()[PANELS_KEY]).toContain('"copilotOpen":true')
  })

  it('ignores broken JSON, unknown values and out-of-range heights', () => {
    expect(loadPanels(memoryStorage({ [PANELS_KEY]: '{' }))).toEqual(DEFAULT_PANELS)
    expect(loadPanels(memoryStorage({
      [PANELS_KEY]: JSON.stringify({ copilotOpen: 'yes', consoleHeight: 5 }),
    }))).toEqual({ ...DEFAULT_PANELS, consoleHeight: 120 })
  })
})
