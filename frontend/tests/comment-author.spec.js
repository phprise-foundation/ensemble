import { describe, it, expect } from 'vitest'
import { resolveAuthor, formatCommentTimestamp } from '../src/lib/comment-author.js'

describe('resolveAuthor', () => {
  it('should resolve user by login', () => {
    const users = {
      'admin': { login: 'admin', displayName: 'Administrator', avatar: 'admin.png', avatarColor: '#e5484d' }
    }
    
    const agents = {}
    const result = resolveAuthor('admin', agents, users)
    
    expect(result.name).toBe('Administrator')
    expect(result.avatar).toBe('admin.png')
    expect(result.avatarColor).toBe('#e5484d')
    expect(result.isUser).toBe(true)
  })
  
  it('should resolve agent by id', () => {
    const users = {}
    const agents = {
      'vivy': { id: 'vivy', name: 'Vivy', portrait: 'vivy.png', avatarColor: '#4c9aff' }
    }
    
    const result = resolveAuthor('vivy', agents, users)
    
    expect(result.name).toBe('Vivy')
    expect(result.avatar).toBe('vivy.png')
    expect(result.avatarColor).toBe('#4c9aff')
    expect(result.isUser).toBe(false)
  })
  
  it('should fallback to agent name when user not found', () => {
    const users = {}
    const agents = {
      'vivy': { id: 'vivy', name: 'Vivy', portrait: 'vivy.png', avatarColor: '#4c9aff' }
    }
    
    const result = resolveAuthor('Vivy', agents, users)
    
    expect(result.name).toBe('Vivy')
    expect(result.avatar).toBe('vivy.png')
    expect(result.avatarColor).toBe('#4c9aff')
    expect(result.isUser).toBe(false)
  })
  
  it('should generate deterministic color for unknown authors', () => {
    const users = {}
    const agents = {}
    
    const result1 = resolveAuthor('unknown-user', agents, users)
    const result2 = resolveAuthor('unknown-user', agents, users)
    
    expect(result1.name).toBe('unknown-user')
    expect(result1.avatar).toBeNull()
    expect(result1.avatarColor).toBe('#6f6463') // Deterministic hash result
    expect(result1.isUser).toBe(false)
    
    // Same input should produce same color
    expect(result1.avatarColor).toBe(result2.avatarColor)
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