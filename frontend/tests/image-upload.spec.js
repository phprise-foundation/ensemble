import { describe, it, expect } from 'vitest'
import { readImageFile, imageFileError } from '../src/lib/image-upload.js'

describe('imageFileError', () => {
  it('retorna erro para arquivo nulo', () => {
    expect(imageFileError(null)).toBe('Choose an image file.')
    expect(imageFileError(undefined)).toBe('Choose an image file.')
  })

  it('retorna erro para tipo inválido', () => {
    const file = { type: 'text/plain', size: 1000 }
    expect(imageFileError(file)).toBe('Use a PNG, JPEG or WebP image.')
  })

  it('retorna erro para arquivo acima do limite', () => {
    const file = { type: 'image/png', size: 2097153 } // 2MB + 1 byte
    expect(imageFileError(file)).toBe('The image must be at most 2 MB.')
  })

  it('não retorna erro para arquivo válido', () => {
    const file = { type: 'image/png', size: 2097152 } // 2MB exato
    expect(imageFileError(file)).toBe('')
  })
})

describe('readImageFile', () => {
  it('devolve data URL para arquivo válido', async () => {
    const file = new File(['fake image content'], 'test.png', { type: 'image/png' })
    
    const result = await readImageFile(file)
    expect(typeof result).toBe('string')
    expect(result).toMatch(/^data:image\/png;base64,/)

    // Testar com diferentes tipos
    const file2 = new File(['fake image content'], 'test.jpg', { type: 'image/jpeg' })
    const result2 = await readImageFile(file2)
    expect(result2).toMatch(/^data:image\/jpeg;base64,/)

    const file3 = new File(['fake image content'], 'test.webp', { type: 'image/webp' })
    const result3 = await readImageFile(file3)
    expect(result3).toMatch(/^data:image\/webp;base64,/)

  })

  it('rejeita arquivo inválido', async () => {
    const file = new File(['fake image content'], 'test.txt', { type: 'text/plain' })
    
    await expect(readImageFile(file)).rejects.toThrow('Use a PNG, JPEG or WebP image.')
  })

  it('rejeita arquivo acima do limite', async () => {
    // Criando um arquivo (com conteúdo real) para que o size funcione corretamente
    const largeBlob = new Blob(['a'.repeat(2097153)], { type: 'image/png' }) // 2MB + 1 byte
    const file = new File([largeBlob], 'test.png', { type: 'image/png' })
    
    await expect(readImageFile(file)).rejects.toThrow('The image must be at most 2 MB.')
  })
})