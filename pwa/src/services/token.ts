/** Auth token holder kept outside Pinia so the HTTP layer has no store dependency. */

let memoryToken: string | null = null
const STORAGE_KEY = 'gego.token'

export function getToken(): string | null {
  if (memoryToken) return memoryToken
  return localStorage.getItem(STORAGE_KEY)
}

export function setToken(token: string | null): void {
  memoryToken = token
  if (token) localStorage.setItem(STORAGE_KEY, token)
  else localStorage.removeItem(STORAGE_KEY)
}
