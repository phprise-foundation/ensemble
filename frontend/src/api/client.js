const base = import.meta.env?.VITE_API_URL || ''

export async function request(method, path, body) {
  const response = await fetch(base + path, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const text = await response.text()
  const data = text ? JSON.parse(text) : null
  if (!response.ok) {
    const error = new Error((data && data.error) || `${response.status} ${response.statusText}`)
    error.status = response.status
    error.field = (data && data.field) || null
    throw error
  }
  return data
}
