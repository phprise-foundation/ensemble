import { boardStore } from './board.js'
import { consoleStore } from './console.js'

export const CATALOG = ['/status', '/list', '/clear', '/help']

function statusIdOf(issue) {
  return issue.statusId ?? issue.status
}

function titleOf(issue) {
  return issue.summary ?? issue.title ?? issue.id
}

function statusOf(issue) {
  return boardStore.status(statusIdOf(issue))
}

function nameOf(issue) {
  return statusOf(issue)?.name ?? '?'
}

function agentsText() {
  const names = boardStore.state.agents.map((agent) => agent.name)
  return names.length > 0 ? names.join(', ') : 'nenhum agente registrado'
}

function countText(status) {
  const count = boardStore.state.issues.filter((issue) => statusIdOf(issue) === status.id).length
  return `${status.name}: ${count}`
}

function statusText() {
  const counts = boardStore.state.statuses.map(countText)
  const header = counts.length > 0 ? 'Itens por status:' : 'Nenhum status carregado.'
  return [`Agentes: ${agentsText()}`, '', header, ...counts].join('\n')
}

function isActive(issue) {
  return statusOf(issue)?.type !== 'final'
}

function itemLine(issue) {
  return `${issue.id} · ${titleOf(issue)} · ${nameOf(issue)}`
}

function listText() {
  const lines = boardStore.state.issues.filter(isActive).map(itemLine)
  return lines.length > 0 ? ['Itens ativos:', ...lines].join('\n') : 'Nenhum item ativo no momento.'
}

function helpText() {
  return [
    `Comandos disponíveis: ${CATALOG.join(', ')}`,
    '/status — resume os agentes e os itens por status',
    '/list — lista os itens ativos do board',
    '/clear — limpa o console (ação local)',
    '/help — mostra este catálogo',
  ].join('\n')
}

const HANDLERS = {
  '/status': statusText,
  '/list': listText,
  '/help': helpText,
}

function commandOf(input) {
  return input.split(/\s+/)[0] ?? ''
}

function unknownText(command) {
  return `Comando desconhecido: ${command}. Catálogo: ${CATALOG.join(', ')}. Use /help.`
}

function respond(text) {
  consoleStore.append({ actor: 'system', kind: 'system', text })
}

function fail(text) {
  consoleStore.append({ actor: 'system', kind: 'error', text })
}

function execute(input) {
  const command = commandOf(input)
  if (command === '/clear') {
    consoleStore.clear()
    return
  }
  consoleStore.append({ actor: 'user', kind: 'command', text: input })
  const handler = HANDLERS[command]
  if (handler) {
    respond(handler())
    return
  }
  fail(unknownText(command))
}

export async function runCommand(rawInput) {
  const input = String(rawInput ?? '').trim()
  if (!input || consoleStore.state.busy) {
    return
  }
  consoleStore.state.busy = true
  try {
    await Promise.resolve().then(() => execute(input))
  } finally {
    consoleStore.state.busy = false
  }
}
