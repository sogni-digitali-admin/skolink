/**
 * Thin fetch wrapper. ALL HTTP goes through here.
 * - Attaches the Sanctum Bearer token.
 * - 401 → notifies the auth store (via onUnauthorized) and throws.
 * - Network failure → ApiError with status 0 so callers can offer offline fallbacks.
 */
import { getToken } from './token'

export class ApiError extends Error {
  readonly status: number
  readonly body: unknown
  readonly isNetwork: boolean

  constructor(message: string, status: number, body: unknown, isNetwork = false) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
    this.isNetwork = isNetwork
  }
}

type UnauthorizedHandler = () => void
const unauthorizedHandlers: UnauthorizedHandler[] = []

export function onUnauthorized(handler: UnauthorizedHandler): void {
  unauthorizedHandlers.push(handler)
}

function emitUnauthorized(): void {
  for (const h of unauthorizedHandlers) h()
}

export interface ApiOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  auth?: boolean
  signal?: AbortSignal
}

export function apiBaseUrl(): string {
  return import.meta.env.VITE_API_BASE_URL ?? ''
}

export async function api<T>(path: string, options: ApiOptions = {}): Promise<T> {
  const { method = 'GET', body, auth = true, signal } = options
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (auth) {
    const token = getToken()
    if (token) headers.Authorization = `Bearer ${token}`
  }

  let res: Response
  try {
    res = await fetch(`${apiBaseUrl()}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    })
  } catch (err) {
    if (err instanceof DOMException && err.name === 'AbortError') throw err
    throw new ApiError('Network request failed', 0, null, true)
  }

  if (res.status === 401) {
    emitUnauthorized()
    throw new ApiError('Unauthenticated', 401, null)
  }

  const text = await res.text()
  let data: unknown = null
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = text
    }
  }

  if (!res.ok) {
    const message =
      typeof data === 'object' && data !== null && 'message' in data
        ? String((data as { message: unknown }).message)
        : `Request failed (${res.status})`
    throw new ApiError(message, res.status, data)
  }

  return data as T
}
