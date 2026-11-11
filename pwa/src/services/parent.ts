import { api } from './http'
import * as EP from './endpoints'
import type {
  AttendanceDay,
  ChildSummary,
  FeeAccount,
  HomeworkItem,
  LeaveItem,
  LeaveType,
  NoticeItem,
  PaymentRequest,
  PaymentResponse,
  PsacResult,
} from './types'

// The legacy API wraps collections in different envelope shapes ({data: [...]},
// plain arrays, {success, data}). This normaliser keeps that ugliness here.
function unwrapList<T>(raw: unknown): T[] {
  if (Array.isArray(raw)) return raw as T[]
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>
    if (Array.isArray(obj.data)) return obj.data as T[]
    if (obj.data && typeof obj.data === 'object' && Array.isArray((obj.data as Record<string, unknown>).data)) {
      return (obj.data as { data: T[] }).data
    }
  }
  return []
}

export async function getChildren(): Promise<ChildSummary[]> {
  const raw = await api<unknown>(EP.MY_CHILDREN)
  return unwrapList<ChildSummary>(raw)
}

export async function getChildDetails(id: number): Promise<Record<string, unknown>> {
  const raw = await api<unknown>(EP.CHILD_DETAILS(id))
  return raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {}
}

export async function getChildAttendance(studentId: number): Promise<AttendanceDay[]> {
  const raw = await api<unknown>(EP.CHILD_ATTENDANCE(studentId))
  return unwrapList<AttendanceDay>(raw)
}

export async function getChildHomework(
  studentId: number,
  state: 'pending' | 'finished',
): Promise<HomeworkItem[]> {
  const raw = await api<unknown>(
    state === 'pending' ? EP.CHILD_HOMEWORK_PENDING(studentId) : EP.CHILD_HOMEWORK_FINISHED(studentId),
  )
  return unwrapList<HomeworkItem>(raw)
}

export async function getSchoolNotices(): Promise<NoticeItem[]> {
  const raw = await api<unknown>(EP.SCHOOL_NOTICES)
  return unwrapList<NoticeItem>(raw)
}

export async function getClassNotices(studentId: number): Promise<NoticeItem[]> {
  const raw = await api<unknown>(EP.CLASS_NOTICES(studentId))
  return unwrapList<NoticeItem>(raw)
}

export async function getNotice(id: number): Promise<NoticeItem | null> {
  const raw = await api<unknown>(EP.NOTICE_SHOW(id))
  if (raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)) {
    return (raw as { data: NoticeItem }).data
  }
  return raw && typeof raw === 'object' ? (raw as NoticeItem) : null
}

export async function getLeaves(studentId: number): Promise<LeaveItem[]> {
  const raw = await api<unknown>(EP.CHILD_LEAVES(studentId))
  return unwrapList<LeaveItem>(raw)
}

export async function getLeaveTypes(): Promise<LeaveType[]> {
  const raw = await api<unknown>(EP.LEAVE_TYPES)
  return unwrapList<LeaveType>(raw)
}

export interface LeaveApplicationInput {
  leaveTypeId: number | null
  fromDate: string
  toDate: string
  reason: string
}

export async function submitLeave(studentId: number, input: LeaveApplicationInput): Promise<void> {
  // Field names mirror the web form consumed by LeaveController@store.
  await api(EP.LEAVE_ADD(studentId), {
    method: 'POST',
    body: {
      leave_type: input.leaveTypeId,
      from_date: input.fromDate,
      to_date: input.toDate,
      reason: input.reason,
    },
  })
}

// ---------- Phase 1 contract (assumed): PSAC-style results ----------

export async function getChildResults(studentId: number): Promise<PsacResult | null> {
  const raw = await api<unknown>(EP.CHILD_RESULTS(studentId))
  if (!raw || typeof raw !== 'object') return null
  const obj = ('data' in (raw as Record<string, unknown>)
    ? (raw as { data: unknown }).data
    : raw) as Record<string, unknown>
  if (!obj || !Array.isArray(obj.subjects)) return null
  return obj as unknown as PsacResult
}

// ---------- Phase 4 contract (assumed, beta): fees & payments ----------

export async function getChildFees(studentId: number): Promise<FeeAccount> {
  const raw = await api<unknown>(EP.CHILD_FEES(studentId))
  const obj = (
    raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)
      ? (raw as { data: unknown }).data
      : raw
  ) as Record<string, unknown> | null
  return {
    studentId,
    currency: (obj?.currency as string | undefined) ?? 'MUR',
    balanceDue: Number(obj?.balanceDue ?? obj?.balance_due ?? 0),
    lines: Array.isArray(obj?.lines) ? (obj.lines as FeeAccount['lines']) : [],
  }
}

export async function createPayment(input: PaymentRequest): Promise<PaymentResponse> {
  const raw = await api<unknown>(EP.PAYMENTS, {
    method: 'POST',
    body: {
      student_id: input.studentId,
      amount: input.amount,
      currency: input.currency,
      method: input.method,
    },
  })
  const obj = (
    raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)
      ? (raw as { data: unknown }).data
      : raw
  ) as Record<string, unknown> | null
  return {
    reference: String(obj?.reference ?? ''),
    checkoutUrl: (obj?.checkoutUrl ?? obj?.checkout_url) as string | undefined,
    status: obj?.status as string | undefined,
  }
}

// ---------- School theming ----------

export interface SchoolInfo {
  name?: string
  themeColor?: string
  logo?: string
}

export async function getSchoolDetails(): Promise<SchoolInfo> {
  const raw = await api<unknown>(EP.SCHOOL_DETAILS)
  const obj = (
    raw && typeof raw === 'object' && 'data' in (raw as Record<string, unknown>)
      ? (raw as { data: unknown }).data
      : raw
  ) as Record<string, unknown> | null
  if (!obj) return {}
  const theme =
    (obj.theme_color as string | undefined) ?? (obj.themeColor as string | undefined) ?? undefined
  return {
    name: (obj.school_name as string | undefined) ?? (obj.name as string | undefined),
    themeColor: theme,
    logo: obj.logo as string | undefined,
  }
}
