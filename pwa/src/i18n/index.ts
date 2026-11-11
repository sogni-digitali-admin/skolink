import { createI18n } from 'vue-i18n'
import en from './locales/en'
import fr from './locales/fr'

export type AppLocale = 'en' | 'fr'
const LOCALE_KEY = 'gego.locale'

export function getStoredLocale(): AppLocale {
  const stored = localStorage.getItem(LOCALE_KEY)
  if (stored === 'en' || stored === 'fr') return stored
  return navigator.language?.toLowerCase().startsWith('fr') ? 'fr' : 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: getStoredLocale(),
  fallbackLocale: 'en',
  messages: { en, fr },
})

export function setLocale(locale: AppLocale): void {
  i18n.global.locale.value = locale
  localStorage.setItem(LOCALE_KEY, locale)
  document.documentElement.lang = locale
}
