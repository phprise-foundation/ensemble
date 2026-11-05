import { describe, it, expect } from 'vitest'
import { plainText } from '../src/lib/plain-text.js'

describe('plainText', () => {
  it('strips markdown syntax and keeps the words', () => {
    const text = '# Title\n\n**bold** and [link](https://x) with `code`\n\n- item one\n- item two'
    expect(plainText(text)).toBe('Title bold and link with code item one item two')
  })

  it('removes html tags, images and fenced code fences', () => {
    expect(plainText('<b>hi</b> ![alt](x.png)')).toBe('hi')
    expect(plainText('before\n```js\nconst x = 1\n```\nafter')).toBe('before const x = 1 after')
  })

  it('collapses whitespace and handles empty input', () => {
    expect(plainText('  a   b  ')).toBe('a b')
    expect(plainText(null)).toBe('')
    expect(plainText(undefined)).toBe('')
  })
})
