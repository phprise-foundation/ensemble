import { boardStore } from './board.js'
import { consoleStore } from './console.js'

const CATALOG = '/status, /list, /help, /clear'

function agentNamesOfTeam(team) {
  const names = boardStore.agentsOf(team.id).map((agent) => agent.name)
  return `${team.name}: ${names.length > 0 ? names.join(', ') : '—'}`
}

function statusText() {
  const agentLines = boardStore.state.teams.map(agentNamesOfTeam)
  const issueLines = activeIssues().map((issue) => `· ${issue.id} ${issue.summary} [${issue.statusId}]`)
  return ['Agentes:', ...agentLines, '', 'Itens ativos:', ...issueLines].join('\n')
}

function statusNameOf(issue) {
  return boardStore.status(issue.statusId)?.name ?? '—'
}

function isActive(issue) {
  const status = boardStore.status(issue.statusId)
  return status === null || status.type !== 'final'
}

function activeIssues() {
  return boardStore.state.issues.filter(isActive)
}

function listText() {
  const lines = activeIssues().map((issue) => {
    const creator = issue.assigneeId ?? issue.ownerId ?? '—'
    const status = statusNameOf(issue)
    return `${issue.id} · ${issue.summary} · ${status} · ${creator}`
  })
  return lines.length > 0 ? ['Itens ativos:', ...lines].join('\n') : 'Nenhum item ativo no momento.'
}

function helpText() {
  return [
    'Comandos disponíveis:',
    '/status — resume o estado dos agentes e dos itens ativos',
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
  consoleStore.append({ actor: 'user', kind: 'command', text: input })
  const command = commandOf(input)
  const handler = handlers[command]
  if (command === '/clear') {
    consoleStore.clear()
    return
  }
  if (!handler) {
    consoleStore.append({ actor: 'system', kind: 'error', text: `Comando desconhecido: ${command}. Catálogo: ${CATALOG}. Use /help.` })
    return
  }
  consoleStore.append({ actor: 'system', kind: 'system', text: handler(input) })
}
