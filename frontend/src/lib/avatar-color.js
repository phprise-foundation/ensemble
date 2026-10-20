const AVATAR_PALETTE = ['#4c9aff', '#00b8d9', '#8777d9', '#ff991f', '#e5484d', '#46a758', '#c95dd8', '#8a7249']

export function avatarColorFor(login) {
  let hash = 2166136261
  for (let index = 0; index < login.length; index += 1) {
    hash = hash ^ login.charCodeAt(index)
    hash = Math.imul(hash, 16777619)
  }
  return AVATAR_PALETTE[(hash >>> 0) % 8]
}
