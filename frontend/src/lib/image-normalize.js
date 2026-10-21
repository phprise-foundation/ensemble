export const IMAGE_MAX_SIDE = 512
export const IMAGE_QUALITY = 0.85

export function fitWithin(width, height, maxSide) {
  if (width <= maxSide && height <= maxSide) {
    return { width, height }
  }
  if (width >= height) {
    return { width: maxSide, height: Math.ceil(height * (maxSide / width)) }
  }
  return { width: Math.ceil(width * (maxSide / height)), height: maxSide }
}

export async function normalizeImageFile(file) {
  const originalUrl = await readFileAsDataURL(file)
  const image = await decodeImage(originalUrl)

  const { width, height } = fitWithin(image.naturalWidth, image.naturalHeight, IMAGE_MAX_SIDE)
  const canvas = document.createElement('canvas')
  canvas.width = width
  canvas.height = height
  canvas.getContext('2d').drawImage(image, 0, 0, width, height)

  const output = canvas.toDataURL('image/webp', IMAGE_QUALITY)
  if (!output.startsWith('data:image/webp')) {
    return keepSmaller(originalUrl, canvas.toDataURL('image/jpeg', IMAGE_QUALITY))
  }
  return keepSmaller(originalUrl, output)
}

function readFileAsDataURL(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result))
    reader.onerror = () => reject(new Error('Could not read the image.'))
    reader.readAsDataURL(file)
  })
}

function decodeImage(url) {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('Could not decode the image.'))
    image.src = url
  })
}

function keepSmaller(original, reencoded) {
  return reencoded.length < original.length ? reencoded : original
}
