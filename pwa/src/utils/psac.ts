import type { PsacBand, PsacGrade } from '@/services/types'

/**
 * Phase 1 contract: PSAC grades 1 (best) → 6 mapped to bands.
 * Thresholds live here only — adjust if the assessment office changes them.
 */
export function gradeBand(grade: PsacGrade): PsacBand {
  if (grade <= 2) return 'proficient'
  if (grade <= 4) return 'intermediate'
  return 'basic'
}
