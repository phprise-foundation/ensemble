import { describe, it, expect } from 'vitest'
import {
  IMAGE_MAX_BYTES,
  IMAGE_TYPES,
  imageFileError,
  readImageFile,
} from '../src/lib/image-upload.js'

const file = (type, size = 4) => new File([new Uint8Array(size)], 'foto', { type })

describe('imageFileError', () => {
  it('exige um arquivo', () => {
    expect(imageFileError(null)).toBe('Choose an image file.')
    expect(imageFileError(undefined)).toBe('Choose an image file.')
  })

  it('aceita PNG, JPEG e WebP até 2 MB', () => {
    for (const type of IMAGE_TYPES) {
      expect(imageFileError(file(type)), type).toBe('')
    }
    expect(imageFileError(file('image/png', IMAGE_MAX_BYTES))).toBe('')
  })

  it('recusa tipo não suportado', () => {
    expect(imageFileError(file('text/plain'))).toBe('Use a PNG, JPEG or WebP image.')
    expect(imageFileError(file('image/gif'))).toBe('Use a PNG, JPEG or WebP image.')
  })

  it('recusa arquivo acima de 2 MB', () => {
    expect(imageFileError(file('image/png', IMAGE_MAX_BYTES + 1))).toBe(
      'The image must be at most 2 MB.',
    )
  })
})

describe('readImageFile', () => {
  it('devolve um data URL para um arquivo válido', async () => {
    const result = await readImageFile(new File(['png'], 'foto.png', { type: 'image/png' }))
    expect(result).toMatch(/^data:image\/png;base64,/)
  })

  it('rejeita arquivo inválido com a mensagem da guarda', async () => {
    await expect(readImageFile(file('text/plain'))).rejects.toThrow(
      'Use a PNG, JPEG or WebP image.',
    )
    await expect(readImageFile(file('image/png', IMAGE_MAX_BYTES + 1))).rejects.toThrow(
      'The image must be at most 2 MB.',
    )
  })
})
