// The headless Electron compositor never advances the Web Animations clock:
// a CSSTransition can sit with `playState: "running"` and `currentTime` frozen
// at 0ms for its whole life, so transitions never complete and elements that
// animate `transform` (e.g. the mobile drawer) stay on their start frame —
// unreachable for the driver. Specs assert state (classes, attributes, DOM),
// not motion; disabling CSS transitions/animations makes every state change
// apply immediately and keeps runs deterministic on any browser path.
const report = { lines: [] }

// Every spec boots the real app: a bearer token from the seeded backend is
// injected before each page load so the shell renders signed in. The
// sign-in gate itself is exercised by the profile-menu spec.
let e2eToken = null
let e2eWorkspaceId = null

before(() => {
  cy.request('POST', '/api/auth/login', { login: 'admin', password: 'admin1234' })
    .its('body.token')
    .then((token) => {
      e2eToken = token
      return cy.request({ url: '/api/workspaces', headers: { Authorization: `Bearer ${e2eToken}` } })
    })
    .its('body.workspaces')
    .then((workspaces) => {
      e2eWorkspaceId = workspaces[0].id
    })
})

Cypress.Commands.add('e2eContext', () => ({ token: e2eToken, workspaceId: e2eWorkspaceId }))

// Workspace-scoped request against the real API with the injected bearer.
Cypress.Commands.add('wsRequest', (method, suffix, body, options = {}) => {
  return cy.request({
    method,
    url: `/api/workspaces/${e2eWorkspaceId}${suffix}`,
    headers: { Authorization: `Bearer ${e2eToken}` },
    ...(body !== undefined ? { body } : {}),
    ...options,
  })
})

Cypress.Commands.add('wsSnapshot', function () {
  return cy
    .request({ url: `/api/workspaces/${e2eWorkspaceId}/snapshot`, headers: { Authorization: `Bearer ${e2eToken}` } })
    .its('body')
})

beforeEach(() => {
  report.lines = []
  if (e2eToken) {
    cy.on('window:before:load', (win) => {
      win.localStorage.setItem('ensemble.token', e2eToken)
    })
  }
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
