import { defineStore } from 'pinia'
import { getChildren } from '@/services/parent'
import type { ChildSummary } from '@/services/types'

const SELECTED_KEY = 'gego.selected_child'

interface ParentState {
  children: ChildSummary[]
  selectedChildId: number | null
  loading: boolean
  error: string | null
}

export const useParentStore = defineStore('parent', {
  state: (): ParentState => ({
    children: [],
    selectedChildId: Number(localStorage.getItem(SELECTED_KEY)) || null,
    loading: false,
    error: null,
  }),
  getters: {
    selectedChild: (s) => s.children.find((c) => c.id === s.selectedChildId) ?? null,
  },
  actions: {
    async loadChildren(force = false): Promise<void> {
      if (this.children.length && !force) return
      this.loading = true
      this.error = null
      try {
        this.children = await getChildren()
        if (!this.selectedChildId && this.children.length) {
          this.selectChild(this.children[0].id)
        }
      } catch (err) {
        this.error = err instanceof Error ? err.message : 'Failed to load children'
      } finally {
        this.loading = false
      }
    },
    selectChild(id: number): void {
      this.selectedChildId = id
      localStorage.setItem(SELECTED_KEY, String(id))
    },
    reset(): void {
      this.children = []
      this.selectedChildId = null
      localStorage.removeItem(SELECTED_KEY)
    },
  },
})
