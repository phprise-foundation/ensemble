const TOKEN = '\u0000'

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

function safeHref(raw) {
  const value = String(raw).trim()
  if (!/^(https?:\/\/|mailto:)/i.test(value)) {
    return null
  }
  try {
    return new URL(value).href
  } catch {
    return null
  }
}

function inline(value) {
  const tokens = []
  const hold = (html) => {
    tokens.push(html)
    return `${TOKEN}${tokens.length - 1}${TOKEN}`
  }

  let text = escapeHtml(value)
  text = text.replace(/`([^`]+)`/g, (_, code) => hold(`<code>${escapeHtml(code)}</code>`))
  text = text.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, href) => {
    const safe = safeHref(href)
    if (!safe) {
      return match
    }
    return hold(`<a href="${escapeHtml(safe)}" target="_blank" rel="noopener noreferrer">${inline(label)}</a>`)
  })
  text = text.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong><em>$1</em></strong>')
  text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
  text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>')
  text = text.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
  text = text.replace(/(^|[^_])_([^_\n]+)_/g, '$1<em>$2</em>')
  text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>')

  return text.replace(new RegExp(`${TOKEN}(\\d+)${TOKEN}`, 'g'), (_, index) => tokens[Number(index)] ?? '')
}

function isHorizontalRule(line) {
  return /^\s*([-*_])(\s*\1){2,}\s*$/.test(line)
}

function listMatch(line) {
  return line.match(/^\s*([-*+]|\d+\.)\s+(.*)$/)
}

export function renderMarkdown(input) {
  const text = String(input ?? '').replace(/\r\n?/g, '\n')
  if (text.trim() === '') {
    return ''
  }

  const lines = text.split('\n')
  const html = []
  let paragraph = []
  let index = 0

  const flush = () => {
    if (paragraph.length > 0) {
      html.push(`<p>${paragraph.map(inline).join('<br>')}</p>`)
      paragraph = []
    }
  }

  while (index < lines.length) {
    const line = lines[index]

    if (/^\s*$/.test(line)) {
      flush()
      index += 1
      continue
    }

    const fence = line.match(/^\s*```\s*([\w+-]*)\s*$/)
    if (fence) {
      flush()
      const code = []
      index += 1
      while (index < lines.length && !/^\s*```\s*$/.test(lines[index])) {
        code.push(lines[index])
        index += 1
      }
      if (index < lines.length) {
        index += 1
      }
      const language = fence[1].replace(/[^\w+-]/g, '')
      const className = language === '' ? '' : ` class="language-${language}"`
      html.push(`<pre><code${className}>${escapeHtml(code.join('\n'))}</code></pre>`)
      continue
    }

    const heading = line.match(/^\s*(#{1,6})\s+(.*)$/)
    if (heading) {
      flush()
      const level = heading[1].length
      html.push(`<h${level}>${inline(heading[2])}</h${level}>`)
      index += 1
      continue
    }

    if (isHorizontalRule(line)) {
      flush()
      html.push('<hr>')
      index += 1
      continue
    }

    if (/^\s*>\s?/.test(line)) {
      flush()
      const quote = []
      while (index < lines.length && /^\s*>\s?/.test(lines[index])) {
        quote.push(lines[index].replace(/^\s*>\s?/, ''))
        index += 1
      }
      html.push(`<blockquote>${quote.map(inline).join('<br>')}</blockquote>`)
      continue
    }

    const list = listMatch(line)
    if (list) {
      flush()
      const ordered = /^\d+\./.test(list[1])
      const items = []
      let item = listMatch(lines[index])
      while (item && /^\d+\./.test(item[1]) === ordered) {
        items.push(item[2])
        index += 1
        item = index < lines.length ? listMatch(lines[index]) : null
      }
      const tag = ordered ? 'ol' : 'ul'
      html.push(`<${tag}>${items.map((entry) => `<li>${inline(entry)}</li>`).join('')}</${tag}>`)
      continue
    }

    paragraph.push(line)
    index += 1
  }

  flush()

  return html.join('\n')
}
