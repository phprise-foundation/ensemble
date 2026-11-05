import { DEFAULT_CONSOLE_HEIGHT, clampHeight } from './consoleHeight.js'

export const PANELS_KEY = 'ensemble.ui.panels'

export const DEFAULT_PANELS = {
  sidebarCollapsed: true,
  copilotOpen: false,
  consoleOpen: false,
  consoleHeight: DEFAULT_CONSOLE_HEIGHT,
}

function storageOrNull(storage) {
  if (storage) return storage
  try {
    return typeof localStorage === 'undefined' ? null : localStorage
  } catch {
    return null
  }
}

function booleanOr(value, fallback) {
  return typeof value === 'boolean' ? value : fallback
}

export function loadPanels(storage) {
  const target = storageOrNull(storage)
  if (!target) return { ...DEFAULT_PANELS }
  try {
    const raw = target.getItem(PANELS_KEY)
    if (!raw) return { ...DEFAULT_PANELS }
    const parsed = JSON.parse(raw)
    const height = Number(parsed.consoleHeight)
    return {
      sidebarCollapsed: booleanOr(parsed.sidebarCollapsed, DEFAULT_PANELS.sidebarCollapsed),
      copilotOpen: booleanOr(parsed.copilotOpen, DEFAULT_PANELS.copilotOpen),
      consoleOpen: booleanOr(parsed.consoleOpen, DEFAULT_PANELS.consoleOpen),
      consoleHeight: clampHeight(Number.isFinite(height) ? height : DEFAULT_PANELS.consoleHeight),
    }
  } catch {
    return { ...DEFAULT_PANELS }
  }
}

export function savePanels(panels, storage) {
  const target = storageOrNull(storage)
  if (!target) return
  try {
    target.setItem(PANELS_KEY, JSON.stringify({ ...DEFAULT_PANELS, ...panels }))
  } catch {
    // A full or private storage must never break the shell.
  }
}
