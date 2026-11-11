import { onMounted, computed, type ComputedRef } from 'vue'
import { useRouter } from 'vue-router'
import { useParentStore } from '@/stores/parent'
import type { ChildSummary } from '@/services/types'

/** Ensures children are loaded and a child is selected; redirects to /p/home otherwise. */
export function useSelectedChild(): { child: ComputedRef<ChildSummary | null> } {
  const parent = useParentStore()
  const router = useRouter()

  onMounted(async () => {
    await parent.loadChildren()
    if (!parent.selectedChild) void router.push({ name: 'parent-home' })
  })

  return { child: computed(() => parent.selectedChild) }
}
