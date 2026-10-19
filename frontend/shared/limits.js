export const DESCRIPTION_LIMIT = 4095
export const COMMENT_LIMIT = 1023

export function countCharacters(value) {
  return Array.from(String(value ?? '')).length
}

export function warningThreshold(limit) {
  return Math.ceil(limit * 0.9)
}

export function nearLimit(length, limit) {
  return length >= warningThreshold(limit) && length <= limit
}

export function limitExceeded(length, limit) {
  return length > limit
}

export function limitError(field, length, limit) {
  const label = field === 'comment' ? 'O comentário' : 'A descrição'
  return `${label} excede o limite de ${limit} caracteres (${length}).`
}
