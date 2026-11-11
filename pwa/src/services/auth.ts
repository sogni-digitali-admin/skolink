import { api } from './http'
import { getDeviceId } from './device'
import {
  PARENT_LOGIN,
  TEACHER_LOGIN,
  PARENT_LOGOUT,
  TEACHER_LOGOUT,
  PARENT_ME,
  TEACHER_ME,
} from './endpoints'
import type { Role, Session } from './types'

interface RawLoginResponse {
  status?: string
  token?: string
  parent_id?: number
  id?: number
  user_name?: string
  user_email?: string
  designation?: string
}

export class LoginFailedError extends Error {
  constructor(message = 'Invalid credentials') {
    super(message)
    this.name = 'LoginFailedError'
  }
}

/**
 * The backend login field is named `email` but actually matched against `mobile_no`
 * (see Api\\TokenController / Api\\Teacher\\LoginController), so we send the mobile
 * number under the `email` key.
 */
export async function login(role: Role, mobile: string, password: string): Promise<Session> {
  const path = role === 'parent' ? PARENT_LOGIN : TEACHER_LOGIN
  let res: RawLoginResponse
  try {
    res = await api<RawLoginResponse>(path, {
      method: 'POST',
      auth: false,
      body: {
        email: mobile,
        password,
        device_id: getDeviceId(),
        device_name: 'pwa',
      },
    })
  } catch (err) {
    if (err instanceof Error && err.name === 'ApiError') throw new LoginFailedError(err.message)
    throw err
  }

  if (!res || typeof res.token !== 'string' || res.token.length === 0) {
    // The legacy controllers return an empty 200 body on bad credentials.
    throw new LoginFailedError()
  }

  return {
    token: res.token,
    role,
    userId: Number(res.parent_id ?? res.id ?? 0),
    userName: res.user_name ?? '',
    userEmail: res.user_email ?? '',
    designation: res.designation,
  }
}

export async function logout(role: Role): Promise<void> {
  const path = role === 'parent' ? PARENT_LOGOUT : TEACHER_LOGOUT
  try {
    await api(path, { method: 'POST' })
  } catch {
    // Best effort — local session is cleared regardless.
  }
}

interface MeResponse {
  data?: Record<string, unknown>
}

/** Fetches the profile of the logged-in user (parent or teacher). */
export async function fetchMe(role: Role): Promise<Record<string, unknown>> {
  const res = await api<MeResponse>(role === 'parent' ? PARENT_ME : TEACHER_ME)
  return res?.data ?? {}
}
