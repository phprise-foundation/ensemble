// The headless Electron compositor never advances the Web Animations clock:
// a CSSTransition can sit with `playState: "running"` and `currentTime` frozen
// at 0ms for its whole life, so transitions never complete and elements that
// animate `transform` (e.g. the mobile drawer) stay on their start frame —
// unreachable for the driver. Specs assert state (classes, attributes, DOM),
// not motion; disabling CSS transitions/animations makes every state change
// apply immediately and keeps runs deterministic on any browser path.
const report = { lines: [] }

beforeEach(() => {
  report.lines = []
})

afterEach(() => {
  const lines = report.lines
  report.lines = []
  expect(lines, `erros de runtime:\n${lines.join('\n')}`).to.have.length(0)
})

Cypress.on('window:before:load', (win) => {
  win.addEventListener('error', (event) => report.lines.push(`error: ${event.message}`))
  win.addEventListener('unhandledrejection', (event) => report.lines.push(`unhandledrejection: ${event.reason}`))
  const consoleError = win.console.error
  win.console.error = (...args) => {
    report.lines.push(`console.error: ${args.join(' ')}`)
    return consoleError.apply(win.console, args)
  }
  const style = win.document.createElement('style')
  style.textContent =
    '*, *::before, *::after { transition: none !important; animation: none !important; }'
  win.document.documentElement.append(style)
})
