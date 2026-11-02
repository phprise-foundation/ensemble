import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import MarkdownText from '../src/components/MarkdownText.vue'
import MarkdownTextarea from '../src/components/MarkdownTextarea.vue'
import { renderMarkdown } from '../src/lib/markdown.js'

describe('markdown renderer', () => {
  it('renders headings, paragraphs, line breaks and lists', () => {
    const html = renderMarkdown('# Title\n\nFirst line\nsecond line\n\n- one\n- two\n\n1. first\n2. second')

    expect(html).toContain('<h1>Title</h1>')
    expect(html).toContain('First line<br>second line')
    expect(html).toContain('<ul><li>one</li><li>two</li></ul>')
    expect(html).toContain('<ol><li>first</li><li>second</li></ol>')
  })

  it('renders emphasis, code, blockquotes and links', () => {
    const html = renderMarkdown('**bold** *italic* `code` ~~gone~~\n\n> quoted\n\n[GitHub](https://github.com/acme)')

    expect(html).toContain('<strong>bold</strong>')
    expect(html).toContain('<em>italic</em>')
    expect(html).toContain('<code>code</code>')
    expect(html).toContain('<del>gone</del>')
    expect(html).toContain('<blockquote>quoted</blockquote>')
    expect(html).toContain('<a href="https://github.com/acme" target="_blank" rel="noopener noreferrer">GitHub</a>')
  })

  it('renders fenced code without interpreting its markdown', () => {
    const html = renderMarkdown('```js\nconst value = **not bold**\n```')

    expect(html).toContain('<pre><code class="language-js">const value = **not bold**</code></pre>')
    expect(html).not.toContain('<strong>')
  })

  it('escapes raw HTML and refuses unsafe URLs', () => {
    const html = renderMarkdown('<script>alert(1)</script> [bad](javascript:alert(1)) <img src=x onerror=alert(1)>')

    expect(html).not.toContain('<script>')
    expect(html).not.toContain('<img')
    expect(html).toContain('&lt;script&gt;')
    expect(html).not.toContain('href="javascript:')
  })

  it('returns an empty string for blank content', () => {
    expect(renderMarkdown('')).toBe('')
    expect(renderMarkdown('   \n  ')).toBe('')
  })
})

describe('MarkdownText and MarkdownTextarea', () => {
  it('renders safe markdown into the component root', () => {
    const wrapper = mount(MarkdownText, { props: { text: '**hello** <b>raw</b>' } })

    expect(wrapper.find('strong').text()).toBe('hello')
    expect(wrapper.find('b').exists()).toBe(false)
    expect(wrapper.text()).toContain('<b>raw</b>')
  })

  it('switches between write and preview without losing the model', async () => {
    const wrapper = mount(MarkdownTextarea, { props: { modelValue: '**hello**', rows: 4 } })

    expect(wrapper.find('textarea').exists()).toBe(true)
    await wrapper.findAll('.markdown-tab')[1].trigger('click')
    expect(wrapper.find('textarea').exists()).toBe(false)
    expect(wrapper.find('.markdown-preview strong').text()).toBe('hello')
    await wrapper.findAll('.markdown-tab')[0].trigger('click')
    expect(wrapper.find('textarea').element.value).toBe('**hello**')
  })
})
