import { defineStore } from 'pinia'
import { login as apiLogin, logout as apiLogout } from '@/services/auth'
import { setToken } from '@/services/token'
import { onUnauthorized } from '@/services/http'
import { useMessagingStore } from '@/stores/messaging'
import type { Role, Session } from '@/services/types'

const STORAGE_KEY = 'gego.session'

interface StoredSession {
  role: Role
  userId: number
  userName: string
  userEmail: string
  designation?: string
}

function loadStored(): StoredSession | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as StoredSession) : null
  } catch {
    return null
  }
}

interface AuthState {
  token: string | null
  role: Role | null
  userId: number
  userName: string
  userEmail: string
  designation?: string
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => {
    const stored = loadStored()
    return {
      token: localStorage.getItem('gego.token'),
      role: stored?.role ?? null,
      userId: stored?.userId ?? 0,
      userName: stored?.userName ?? '',
      userEmail: stored?.userEmail ?? '',
      designation: stored?.designation,
    }
  },
  getters: {
    isAuthenticated: (s) => s.token !== null && s.role !== null,
    homeRoute: (s) => (s.role === 'teacher' ? '/t/home' : '/p/home'),
  },
  actions: {
    async login(role: Role, mobile: string, password: string): Promise<void> {
      const session: Session = await apiLogin(role, mobile, password)
      this.applySession(session)
    },
    applySession(session: Session): void {
      setToken(session.token)
      this.token = session.token
      this.role = session.role
      this.userId = session.userId
      this.userName = session.userName
      this.userEmail = session.userEmail
      this.designation = session.designation
      const stored: StoredSession = {
        role: session.role,
        userId: session.userId,
        userName: session.userName,
        userEmail: session.userEmail,
        designation: session.designation,
      }
      localStorage.setItem(STORAGE_KEY, JSON.stringify(stored))
      // Realtime + unread badge live for the whole session.
      void useMessagingStore().start()
    },
    async logout(callApi = true): Promise<void> {
      const role = this.role
      this.clear()
      if (callApi && role) await apiLogout(role)
    },
    clear(): void {
      void useMessagingStore().stop()
      setToken(null)
      localStorage.removeItem(STORAGE_KEY)
      this.token = null
      this.role = null
      this.userId = 0
      this.userName = ''
      this.userEmail = ''
      this.designation = undefined
    },
    /** Wired to the HTTP layer: any 401 anywhere logs the user out. */
    bindUnauthorized(redirect: () => void): void {
      onUnauthorized(() => {
        this.clear()
        redirect()
      })
    },
  },
})
