// The headless Electron compositor never advances the Web Animations clock:
// a CSSTransition can sit with `playState: "running"` and `currentTime` frozen
// at 0ms for its whole life, so transitions never complete and elements that
// animate `transform` (e.g. the mobile drawer) stay on their start frame —
// unreachable for the driver. Specs assert state (classes, attributes, DOM),
// not motion; disabling CSS transitions/animations makes every state change
// apply immediately and keeps runs deterministic on any browser path.
Cypress.on('window:before:load', (win) => {
  const style = win.document.createElement('style')
  style.textContent =
    '*, *::before, *::after { transition: none !important; animation: none !important; }'
  win.document.documentElement.append(style)
})
