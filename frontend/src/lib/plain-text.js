const FENCE = /```[\s\S]*?```/g
const IMAGE = /!\[[^\]]*\]\([^)]*\)/g
const LINK = /\[([^\]]+)\]\([^)]*\)/g
const HTML_TAG = /<[^>]+>/g
const INLINE_CODE = /`([^`]+)`/g
const EMPHASIS = /(\*\*|__|\*|_|~~)/g
const HEADING = /^\s{0,3}#{1,6}\s+/gm
const QUOTE = /^\s{0,3}>\s?/gm
const LIST_MARKER = /^\s{0,3}([-*+]|\d+[.)])\s+/gm
const RULE = /^\s{0,3}([-*_])(\s*\1){2,}\s*$/gm

export function plainText(input) {
  return String(input ?? '')
    .replace(FENCE, (block) => block.replace(/```[\w-]*/g, ' '))
    .replace(IMAGE, ' ')
    .replace(LINK, '$1')
    .replace(HTML_TAG, ' ')
    .replace(INLINE_CODE, '$1')
    .replace(HEADING, '')
    .replace(QUOTE, '')
    .replace(RULE, ' ')
    .replace(LIST_MARKER, '')
    .replace(EMPHASIS, '')
    .replace(/\s+/g, ' ')
    .trim()
}
