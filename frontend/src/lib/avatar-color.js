const PALETTE = ['#4c9aff', '#00b8d9', '#8777d9', '#ff991f', '#e5484d', '#46a758', '#c95dd8', '#8a7249']

function fnv1a(value) {
  let hash = 0x811c9dc5
  for (const character of String(value)) {
    hash ^= character.codePointAt(0)
    hash = Math.imul(hash, 0x01000193) >>> 0
  }
  return hash
}

export function avatarColorFor(login) {
  return PALETTE[fnv1a(login) % PALETTE.length]
}
