const SAME_ORIGIN = ''

export function isRealBackend() {
  return Boolean(import.meta.env?.VITE_API_URL)
}

export function token() {
  if (typeof localStorage === 'undefined') {
    return null
  }
  return localStorage.getItem('ensemble.token')
}

export function setToken(value) {
  if (typeof localStorage === 'undefined') {
    return
  }
  if (value) {
    localStorage.setItem('ensemble.token', value)
  } else {
    localStorage.removeItem('ensemble.token')
  }
}

export async function request(method, path, body) {
  const bearer = token()
  const headers = { 'Content-Type': 'application/json' }
  if (bearer) {
    headers.Authorization = `Bearer ${bearer}`
  }
  const response = await fetch(SAME_ORIGIN + path, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  const text = await response.text()
  const data = text ? JSON.parse(text) : null
  if (!response.ok) {
    if (response.status === 401) {
      setToken(null)
    }
    const error = new Error((data && data.error) || `${response.status} ${response.statusText}`)
    error.status = response.status
    error.field = (data && data.field) || null
    throw error
  }
  return data
}
