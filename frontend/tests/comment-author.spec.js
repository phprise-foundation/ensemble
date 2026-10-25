import { describe, it, expect } from 'vitest'
import { resolveAuthor, formatCommentTimestamp } from '../src/lib/comment-author.js'

const user = { login: 'admin', displayName: 'Administrator', avatar: 'admin.png', avatarColor: '#e5484d' }
const vivy = { id: 'vivy', name: 'Vivy', portrait: 'vivy.png', avatarColor: '#4c9aff' }

describe('resolveAuthor', () => {
  it('should resolve user by login', () => {
    const result = resolveAuthor({ agents: [], user, author: 'admin' })

    expect(result.name).toBe('Administrator')
    expect(result.image).toBe('admin.png')
    expect(result.color).toBe('#e5484d')
    expect(result.kind).toBe('user')
  })

  it('should resolve agent by id', () => {
    const result = resolveAuthor({ agents: [vivy], user, author: 'vivy' })

    expect(result.name).toBe('Vivy')
    expect(result.image).toBe('vivy.png')
    expect(result.color).toBe('#4c9aff')
    expect(result.kind).toBe('agent')
  })

  it('should fallback to agent name when user not found', () => {
    const result = resolveAuthor({ agents: [vivy], user, author: 'Vivy' })

    expect(result.name).toBe('Vivy')
    expect(result.image).toBe('vivy.png')
    expect(result.color).toBe('#4c9aff')
    expect(result.kind).toBe('agent')
  })

  it('should generate deterministic color for unknown authors', () => {
    const result1 = resolveAuthor({ agents: [], user, author: 'unknown-user' })
    const result2 = resolveAuthor({ agents: [], user, author: 'unknown-user' })

    expect(result1.name).toBe('unknown-user')
    expect(result1.image).toBeNull()
    expect(result1.kind).toBe('unknown')
    expect(result1.color).toMatch(/^#[0-9a-f]{6}$/)
    expect(result1.color).toBe(result2.color)
  })
})

describe('formatCommentTimestamp', () => {
  it('should format timestamp correctly', () => {
    const date = new Date('2026-10-21T12:07:00')
    const result = formatCommentTimestamp(date.toISOString())

    expect(result).toBe('21/10/2026 12:07')
  })

  it('should zero-pad single digit values', () => {
    const date = new Date('2026-01-02T05:07:00')
    const result = formatCommentTimestamp(date.toISOString())

    expect(result).toBe('02/01/2026 05:07')
  })
})
