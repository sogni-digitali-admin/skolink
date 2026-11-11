/**
 * Captures `beforeinstallprompt` and exposes it to the UI.
 * The banner is only shown after a "first value moment" (markValueMoment()),
 * never on first paint.
 */
import { defineStore } from 'pinia'

interface BeforeInstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const VALUE_KEY = 'gego.value_seen'
const DISMISS_KEY = 'gego.install_dismissed'

interface InstallState {
  deferredPrompt: BeforeInstallPromptEvent | null
  installed: boolean
  valueSeen: boolean
  dismissed: boolean
}

export const useInstallStore = defineStore('install', {
  state: (): InstallState => ({
    deferredPrompt: null,
    installed: false,
    valueSeen: localStorage.getItem(VALUE_KEY) === '1',
    dismissed: localStorage.getItem(DISMISS_KEY) === '1',
  }),
  getters: {
    canShowBanner: (s) =>
      !s.installed && !s.dismissed && s.valueSeen && s.deferredPrompt !== null,
    /** iOS Safari has no beforeinstallprompt — show manual instructions instead. */
    isIos: () =>
      /iphone|ipad|ipod/i.test(navigator.userAgent) &&
      !(navigator as Navigator & { standalone?: boolean }).standalone,
  },
  actions: {
    capture(event: Event): void {
      event.preventDefault()
      this.deferredPrompt = event as BeforeInstallPromptEvent
    },
    markValueMoment(): void {
      if (this.valueSeen) return
      this.valueSeen = true
      localStorage.setItem(VALUE_KEY, '1')
    },
    async promptInstall(): Promise<void> {
      if (!this.deferredPrompt) return
      await this.deferredPrompt.prompt()
      const choice = await this.deferredPrompt.userChoice
      if (choice.outcome === 'accepted') this.installed = true
      this.deferredPrompt = null
    },
    dismiss(): void {
      this.dismissed = true
      localStorage.setItem(DISMISS_KEY, '1')
    },
  },
})
