import { existsSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import assert from 'node:assert/strict'
import { test } from 'node:test'

import { createSeed } from '../src/data/seed.js'

const here = dirname(fileURLToPath(import.meta.url))
const frontend = join(here, '..')

function source(relativePath) {
  const full = join(frontend, relativePath)
  return existsSync(full) ? readFileSync(full, 'utf8') : ''
}

async function commentAuthor() {
  try {
    return await import('../src/lib/comment-author.js')
  } catch {
    return null
  }
}

async function avatarColorFor() {
  try {
    const module = await import('../src/lib/avatar-color.js')
    return module.avatarColorFor
  } catch {
    return null
  }
}

const agents = [
  { id: 'vivy', name: 'Vivy', portrait: 'data:image/png;base64,iVBORw0KGgo=', avatarColor: '#4c9aff' },
  { id: 'kaio', name: 'Kaio', portrait: null, avatarColor: '#ff991f' },
  { id: 'dup', name: 'Dup', portrait: null, avatarColor: '#8777d9' },
]

async function requireAvatarColorFor() {
  const resolve = await avatarColorFor()
  assert.ok(resolve, 'avatar-color deve existir em src/lib/avatar-color.js (KAN-130)')
  return resolve
}

const user = {
  login: 'admin',
  displayName: 'Admin Master',
  avatar: 'data:image/png;base64,iVBORw0KGgo=',
  avatarColor: '#00b8d9',
}

test('[RED] lib comment-author expõe resolveAuthor e formatCommentTimestamp', async () => {
  const module = await commentAuthor()
  assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
  assert.equal(typeof module.resolveAuthor, 'function', 'resolveAuthor deve ser exportada')
  assert.equal(typeof module.formatCommentTimestamp, 'function', 'formatCommentTimestamp deve ser exportada')
})

test('[RED] resolveAuthor: agente por id retorna retrato e cor do agente', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  assert.deepEqual(
    module.resolveAuthor({ agents, user, author: 'vivy' }),
    { kind: 'agent', name: 'Vivy', image: 'data:image/png;base64,iVBORw0KGgo=', color: '#4c9aff' },
    'o author id do agente resolve com o portrait do seed e a cor do agente',
  )
})

test('[RED] resolveAuthor: agente por nome quando o id não casa', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  assert.deepEqual(
    module.resolveAuthor({ agents, user, author: 'Kaio' }),
    { kind: 'agent', name: 'Kaio', image: null, color: '#ff991f' },
    'o nome do agente também resolve; sem portrait, image é null e não undefined',
  )
})

test('[RED] resolveAuthor: user por login e por displayName', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  assert.deepEqual(
    module.resolveAuthor({ agents, user, author: 'admin' }),
    { kind: 'user', name: 'Admin Master', image: 'data:image/png;base64,iVBORw0KGgo=', color: '#00b8d9' },
    'login casa com a sessão e o nome visível é o displayName',
  )
  assert.deepEqual(
    module.resolveAuthor({ agents, user: { ...user, displayName: '' }, author: 'Admin Master' }),
    { kind: 'unknown', name: 'Admin Master', image: null, color: (await requireAvatarColorFor())('Admin Master') },
    'com displayName vazio o displayName não resolve e o fallback assume',
  )
  assert.deepEqual(
    module.resolveAuthor({ agents, user: { ...user, displayName: 'Admin Master' }, author: 'Admin Master' }),
    { kind: 'user', name: 'Admin Master', image: 'data:image/png;base64,iVBORw0KGgo=', color: '#00b8d9' },
    'displayName do user resolve quando existir',
  )
})

test('[RED] resolveAuthor: colisão entre id de agente e login do user', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  const conflicting = { ...user, login: 'dup' }
  assert.deepEqual(
    module.resolveAuthor({ agents, user: conflicting, author: 'dup' }),
    { kind: 'agent', name: 'Dup', image: null, color: '#8777d9' },
    'o id do agente vence a colisão com o login do user',
  )
})

test('[RED] resolveAuthor: desconhecido usa a string original com cor determinística', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  const colorFor = await requireAvatarColorFor()
  assert.deepEqual(
    module.resolveAuthor({ agents, user, author: 'user' }),
    { kind: 'unknown', name: 'user', image: null, color: colorFor('user') },
    'o comentário legado "user" cai no fallback com a cor determinística',
  )
  assert.equal(
    module.resolveAuthor({ agents, user, author: 'user' }).color,
    module.resolveAuthor({ agents, user, author: 'user' }).color,
    'a cor do fallback é estável entre chamadas iguais',
  )
})

test('[RED] formatCommentTimestamp: absoluto dd/MM/yyyy HH:mm, nunca relativo', async () => {
  const module = await commentAuthor()
  if (!module) {
    assert.ok(module, 'a lib deve existir em src/lib/comment-author.js (KAN-128 onda 1)')
    return
  }
  const iso = '2026-03-05T01:02:03Z'
  const anchored = '2026-03-05T01:02:03+05:30'
  const pattern = /^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/
  assert.match(module.formatCommentTimestamp(iso), pattern, 'ISO com Z sai no formato absoluto com pad 2')
  assert.match(module.formatCommentTimestamp(anchored), pattern, 'ISO com offset sai no mesmo formato')
  assert.doesNotMatch(module.formatCommentTimestamp(iso), /há|ago|atrás|relativo/, 'sem formato relativo')
  assert.equal(
    module.formatCommentTimestamp(iso),
    module.formatCommentTimestamp(iso),
    'o formato é estável para o mesmo instante',
  )
})

test('[RED] store: addComment envia o login da sessão como author', () => {
  const board = source('src/store/board.js')
  assert.match(
    board,
    /addComment[\s\S]{0,200}author:\s*session\.login/,
    'boardStore.addComment deve enviar author: session.login (contrato da decisão 2)',
  )
  assert.doesNotMatch(
    board,
    /addComment[\s\S]{0,200}author:\s*'user'/,
    "o literal fixo 'user' sai do POST",
  )
})

test('[RED] IssuePanel: comentários com autor resolvido e timestamp determinístico', () => {
  const panel = source('src/components/IssuePanel.vue')
  assert.match(
    panel,
    /resolveAuthor/,
    'o painel deve resolver o autor pela lib comment-author',
  )
  assert.match(
    panel,
    /formatCommentTimestamp/,
    'o painel deve usar formatCommentTimestamp no lugar do toLocaleString flutuante',
  )
  assert.doesNotMatch(panel, /toLocaleString/, 'o formato flutuante some desta entrega')
  assert.match(panel, /class="[^"]*comment-avatar/, 'cada linha de comentário tem a miniatura do autor')
  assert.match(panel, /class="[^"]*comment-header|class="[^"]*comment-caption/, 'a legenda do autor existe na linha')
})

test('[RED] IssueCard: avatar circular via componente compartilhado com tooltip', () => {
  const card = source('src/components/IssueCard.vue')
  assert.match(card, /<Avatar/, 'o card deve consumir o componente Avatar do KAN-108')
  assert.match(card, /:title="agent\?\.name/, 'o assignee tem tooltip com o nome do agente')
  assert.doesNotMatch(card, /class="avatar sm"/, 'o avatar retangular arredondado do card sai de cena')
})

test('[RED] seed cobre as ramificações do mapeamento e o data URL pequeno', () => {
  const db = createSeed()
  const kanb1 = db.issues.find((issue) => issue.id === 'KANB-1')
  const authors = (kanb1?.comments ?? []).map((entry) => entry.author)
  for (const branch of ['admin', 'vivy', 'Kaio', 'user']) {
    assert.ok(authors.includes(branch), `o seed deve ter comentário com author "${branch}" (encontradas: ${authors.join(', ')})`)
  }
  const timestamps = (kanb1?.comments ?? []).map((entry) => new Date(entry.at).getTime())
  assert.deepEqual(timestamps, [...timestamps].sort((a, b) => a - b), 'a ordem cronológica dos comentários é crescente')
  const portraits = db.agents.filter((agent) => typeof agent.portrait === 'string' && agent.portrait.startsWith('data:'))
  assert.ok(portraits.length > 0, 'pelo menos um agente deve ter imagem em data URL (KAN-108/onda 2)')
  for (const agent of portraits) {
    assert.ok(
      agent.portrait.length <= 10 * 1024,
      `o data URL do agente ${agent.id} deve ter no máximo 10 KB (hoje: ${Math.round(agent.portrait.length / 1024)} KB)`,
    )
  }
})
