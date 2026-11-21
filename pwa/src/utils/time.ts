/** Date helpers for chat UI — pure functions, i18n labels stay in the views. */

export type DayKind = 'today' | 'yesterday' | 'date'

function toDate(iso: string): Date | null {
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? null : d
}

function startOfDay(d: Date): Date {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate())
}

/** Classify an ISO timestamp against "now" for day separators. */
export function dayKind(iso: string, now = new Date()): DayKind {
  const d = toDate(iso)
  if (!d) return 'date'
  const diffDays = Math.round((startOfDay(now).getTime() - startOfDay(d).getTime()) / 86_400_000)
  if (diffDays <= 0) return 'today'
  if (diffDays === 1) return 'yesterday'
  return 'date'
}

export function sameDay(a: string, b: string): boolean {
  const da = toDate(a)
  const db = toDate(b)
  if (!da || !db) return false
  return startOfDay(da).getTime() === startOfDay(db).getTime()
}

/** HH:MM for bubble timestamps. */
export function timeLabel(iso: string, locale: string): string {
  const d = toDate(iso)
  if (!d) return ''
  return d.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
}

/** Short date for separators older than yesterday. */
export function dateLabel(iso: string, locale: string): string {
  const d = toDate(iso)
  if (!d) return ''
  return d.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' })
}

/** Compact relative label for the conversation list ("14:32", "Yesterday", "9 Nov"). */
export function listTimeLabel(iso: string, locale: string, yesterday: string): string {
  const kind = dayKind(iso)
  if (kind === 'today') return timeLabel(iso, locale)
  if (kind === 'yesterday') return yesterday
  return dateLabel(iso, locale)
}
