export const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp']
export const IMAGE_MAX_BYTES = 2 * 1024 * 1024

export function imageFileError(file) {
  if (!file) {
    return 'Choose an image file.'
  }
  if (!IMAGE_TYPES.includes(file.type)) {
    return 'Use a PNG, JPEG or WebP image.'
  }
  if (file.size > IMAGE_MAX_BYTES) {
    return 'The image must be at most 2 MB.'
  }
  return ''
}

export function readImageFile(file) {
  const problem = imageFileError(file)
  if (problem) {
    return Promise.reject(new Error(problem))
  }
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(new Error('Could not read the image.'))
    reader.readAsDataURL(file)
  })
}
