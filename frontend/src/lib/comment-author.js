import { avatarColorFor } from './avatar-color.js'

const agentById = (agents, author) => agents.find((agent) => agent.id === author) ?? null
const agentByName = (agents, author) => agents.find((agent) => agent.name === author) ?? null

const agentAuthor = (agent) => ({
  kind: 'agent',
  name: agent.name,
  image: agent.portrait ?? null,
  color: agent.avatarColor,
})

const userAuthor = (user) => ({
  kind: 'user',
  name: user.displayName || user.login,
  image: user.avatar ?? null,
  color: user.avatarColor,
})

const unknownAuthor = (author) => ({
  kind: 'unknown',
  name: author,
  image: null,
  color: avatarColorFor(author),
})

export function resolveAuthor({ agents, user, author }) {
  const agent = agentById(agents, author) ?? agentByName(agents, author)
  if (agent) {
    return agentAuthor(agent)
  }
  if (user && (user.login === author || user.displayName === author)) {
    return userAuthor(user)
  }
  return unknownAuthor(author)
}

const pad = (value) => String(value).padStart(2, '0')

export function formatCommentTimestamp(at) {
  const date = new Date(at)
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}
