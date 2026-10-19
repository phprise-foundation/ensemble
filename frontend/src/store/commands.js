import { boardStore } from './board.js'
import { consoleStore } from './console.js'

export const CATALOG = ['/status', '/list', '/clear', '/help']

function nameOf(statusId) {
  return boardStore.status(statusId)?.name ?? statusId
}

function isActive(issue) {
  return boardStore.status(issue.statusId)?.type !== 'final'
}

function activeIssues() {
  return boardStore.state.issues.filter(isActive)
}

function agentsText() {
  const names = boardStore.state.agents.map((agent) => agent.name)
  return names.length > 0 ? names.join(', ') : 'nenhum agente registrado'
}

function groupedText(issues) {
  const names = [...new Set(issues.map((issue) => nameOf(issue.statusId)))]
  return names.map((name) => {
    const ids = issues.filter((issue) => nameOf(issue.statusId) === name).map((issue) => issue.id)
    return `${name}: ${ids.join(', ')}`
  })
}

function statusText() {
  const active = activeIssues()
  const header = active.length > 0 ? 'Itens ativos:' : 'Nenhum item ativo no momento.'
  return [`Agentes: ${agentsText()}`, '', header, ...groupedText(active)].join('\n')
}

function listText() {
  const lines = activeIssues().map((issue) => `${issue.id} · ${issue.summary} · ${nameOf(issue.statusId)}`)
  return lines.length > 0 ? ['Itens ativos:', ...lines].join('\n') : 'Nenhum item ativo no momento.'
}

function helpText() {
  return [
    `Comandos disponíveis: ${CATALOG.join(', ')}`,
    '/status — resume os agentes e os itens ativos por status',
    '/list — lista os itens ativos do board',
    '/clear — limpa o console (ação local)',
    '/help — mostra este catálogo',
  ].join('\n')
}

const handlers = {
  '/status': statusText,
  '/list': listText,
  '/help': helpText,
}

function commandOf(rawInput) {
  return rawInput.split(/\s+/)[0]
}

export async function runCommand(rawInput) {
  const input = rawInput.trim()
  if (!input || consoleStore.state.busy) {
    return
  }
  consoleStore.state.busy = true
  try {
    await execute(input)
  } finally {
    consoleStore.state.busy = false
  }
}

async function execute(input) {
  const command = commandOf(input)
  if (command === '/clear') {
    consoleStore.clear()
    return
  }
  consoleStore.append({ actor: 'user', kind: 'command', text: input })
  await Promise.resolve()
  const handler = handlers[command]
  if (!handler) {
    const text = `Comando desconhecido: ${command}. Catálogo: ${CATALOG.join(', ')}. Use /help.`
    consoleStore.append({ actor: 'system', kind: 'error', text })
    return
  }
  consoleStore.append({ actor: 'system', kind: 'system', text: handler() })
}
