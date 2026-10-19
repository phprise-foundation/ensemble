import { boardStore } from './board.js'
import { consoleStore } from './console.js'

const COMMANDS = '/status /list /clear /help'

function agentLine(team) {
  const names = boardStore.agentsOf(team.id).map((agent) => agent.name)
  return `${team.name}: ${names.length > 0 ? names.join(', ') : '—'}`
}

function statusText() {
  const agents = boardStore.state.teams.map(agentLine)
  const items = boardStore.state.statuses
    .filter((status) => status.workflowId === boardStore.board.value?.workflowId)
    .map((status) => {
      const count = boardStore.state.issues.filter((issue) => issue.statusId === status.id).length
      return `${status.name}: ${count}`
    })
  return ['Agentes:', ...agents, '', 'Itens por status:', ...items].join('\n')
}

function isActive(issue) {
  const status = boardStore.status(issue.statusId)
  return Boolean(status && status.type !== 'final')
}

function listText() {
  const lines = boardStore.state.issues
    .filter(isActive)
    .map((issue) => `${issue.id} · ${issue.summary} · ${boardStore.status(issue.statusId)?.name ?? '?'}`)
  return lines.length > 0 ? ['Itens ativos:', ...lines].join('\n') : 'Nenhum item ativo no momento.'
}

function helpText() {
  return [
    'Comandos disponíveis:',
    '/status — resume agentes e itens por status',
    '/list — lista os itens ativos',
    '/clear — limpa o console (ação local)',
    '/help — mostra este catálogo',
  ].join('\n')
}

function clearText() {
  consoleStore.clear()
  return 'Console limpo.'
}

const HANDLERS = {
  '/status': statusText,
  '/list': listText,
  '/clear': clearText,
  '/help': helpText,
}

function commandOf(input) {
  return input.split(/\s+/)[0] ?? ''
}

function unknownText(command) {
  return `Comando desconhecido: ${command}. Você quis dizer ${COMMANDS}? Use /help para ver o catálogo.`
}

export function runCommand(rawInput) {
  const input = rawInput.trim()
  if (!input) {
    return
  }
  consoleStore.append('command', 'user', input)
  const command = commandOf(input)
  const handler = HANDLERS[command]
  if (!handler) {
    consoleStore.append('error', 'system', unknownText(command))
    return
  }
  consoleStore.state.busy = true
  try {
    consoleStore.append('system', 'system', handler())
  } finally {
    consoleStore.state.busy = false
  }
}
