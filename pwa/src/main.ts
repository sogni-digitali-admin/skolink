import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { registerSW } from 'virtual:pwa-register'
import App from './App.vue'
import { router } from './router'
import { i18n } from './i18n'
import { useAuthStore } from './stores/auth'
import { useSyncStore } from './stores/sync'
import { useInstallStore } from './stores/install'
import { flushOutbox } from './offline/outbox'
import './style.css'

registerSW({ immediate: true })

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.use(i18n)

// Any 401 from the HTTP layer logs out and returns to the login screen.
const auth = useAuthStore()
auth.bindUnauthorized(() => {
  void router.push({ name: 'login' })
})

// Connectivity + outbox wiring.
const sync = useSyncStore()
window.addEventListener('online', () => sync.setOnline(true))
window.addEventListener('offline', () => sync.setOnline(false))

// Background Sync: the service worker nudges open clients to flush the outbox.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('message', (event: MessageEvent) => {
    const data = event.data as { type?: string } | undefined
    if (data?.type === 'SYNC_ATTENDANCE') void flushOutbox().then(() => sync.refreshPending())
  })
}

// Install prompt: capture early, show only after a value moment.
const install = useInstallStore()
window.addEventListener('beforeinstallprompt', (e) => install.capture(e))
window.addEventListener('appinstalled', () => {
  install.installed = true
})

void sync.refreshPending()

app.mount('#app')
