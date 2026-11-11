import { defineStore } from 'pinia'
import { flushOutbox, countPending } from '@/offline/outbox'
import { registerAttendanceSync } from '@/services/push'

interface SyncState {
  online: boolean
  syncing: boolean
  pendingCount: number
  lastSyncAt: number | null
  lastError: string | null
}

export const useSyncStore = defineStore('sync', {
  state: (): SyncState => ({
    online: typeof navigator === 'undefined' ? true : navigator.onLine,
    syncing: false,
    pendingCount: 0,
    lastSyncAt: null,
    lastError: null,
  }),
  actions: {
    async refreshPending(): Promise<void> {
      this.pendingCount = await countPending()
    },
    setOnline(online: boolean): void {
      this.online = online
      if (online) void this.flush()
    },
    async flush(): Promise<void> {
      if (this.syncing) return
      this.syncing = true
      this.lastError = null
      try {
        const result = await flushOutbox()
        this.pendingCount = result.remaining
        if (result.synced > 0) this.lastSyncAt = Date.now()
        await registerAttendanceSync()
      } catch (err) {
        this.lastError = err instanceof Error ? err.message : 'Sync failed'
      } finally {
        this.syncing = false
      }
    },
  },
})
