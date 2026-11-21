/** Shared API/domain types. Fields match the Laravel resources where known. */

export type Role = 'parent' | 'teacher'

export interface Session {
  token: string
  role: Role
  userId: number
  userName: string
  userEmail: string
  /** teacher-only extra info */
  designation?: string
}

export interface ChildSummary {
  id: number
  name: string
  className?: string
  avatar?: string
  [key: string]: unknown
}

export interface AttendanceDay {
  date: string
  session?: string
  status: 'present' | 'absent' | 'late' | 'leave' | string
  reason?: string
  remarks?: string
  [key: string]: unknown
}

export interface HomeworkItem {
  id: number
  title: string
  subject?: string
  description?: string
  dueDate?: string
  status?: string
  [key: string]: unknown
}

export interface NoticeItem {
  id: number
  title: string
  body?: string
  date?: string
  audience?: string
  [key: string]: unknown
}

export interface LeaveItem {
  id: number
  fromDate?: string
  toDate?: string
  reason?: string
  status?: string
  [key: string]: unknown
}

export interface LeaveType {
  id: number
  name: string
  [key: string]: unknown
}

/** Phase 1 contract: PSAC-style results. Grade 1 (best) → 6. */
export type PsacGrade = 1 | 2 | 3 | 4 | 5 | 6
export type PsacBand = 'proficient' | 'intermediate' | 'basic'

export interface PsacSubjectResult {
  subject: string
  grade: PsacGrade
}

export interface PsacResult {
  studentId: number
  examName: string
  year: string | number
  subjects: PsacSubjectResult[]
  overallGrade?: PsacGrade
}

/** Phase 4 contract (beta): fees & payments. */
export interface FeeLine {
  id: number | string
  label: string
  amount: number
  currency: string
  dueDate?: string
  status: 'due' | 'paid' | 'overdue' | string
}

export interface FeeAccount {
  studentId: number
  currency: string
  balanceDue: number
  lines: FeeLine[]
}

export interface PaymentRequest {
  studentId: number
  amount: number
  currency: string
  method: 'card' | 'juice' | 'mcib' | string
}

export interface PaymentResponse {
  reference: string
  checkoutUrl?: string
  status?: string
}

/** Teacher-side roster. */
export interface TeacherClass {
  standardLinkId: number
  className: string
  section?: string
  subjectName?: string
}

export interface RosterStudent {
  id: number
  name: string
  rollNo?: string | number
  avatar?: string
}

export interface AbsentReason {
  id: number
  name: string
}

export interface AttendanceRoster {
  classes: TeacherClass[]
  students: RosterStudent[]
  absentReasons: AbsentReason[]
}

/** Payload shape required by the existing AttendanceController@store. */
export interface AttendanceSubmitPayload {
  Standardlinkid: number
  Date: string
  Session: 'morning' | 'afternoon' | string
  AbsentList: Array<{ student_id: number; reasonId: number | null; remarks: string }>
}

export interface TeacherHomeworkMeta {
  classes: TeacherClass[]
  [key: string]: unknown
}

export interface SubmissionItem {
  id: number
  studentName: string
  submittedAt?: string
  answer?: string
  grade?: string | number | null
  remarks?: string | null
  [key: string]: unknown
}

/** Web Push subscription body (Phase 2 contract — mirrors PushSubscription JSON). */
export interface PushSubscriptionBody {
  endpoint: string
  expirationTime: number | null
  keys: { p256dh: string; auth: string }
}

// ---------- Messaging (Phase contract — B3 implements this verbatim) ----------

export interface ConversationParticipant {
  id: number
  name: string
  role: string
  avatarUrl?: string
}

export interface ConversationLastMessage {
  body: string
  createdAt: string
  isOwn: boolean
}

export interface ConversationSummary {
  uuid: string
  otherParticipant: ConversationParticipant
  lastMessage?: ConversationLastMessage
  unreadCount: number
  updatedAt: string
}

export interface ConversationMessage {
  id: number
  body: string
  isOwn: boolean
  createdAt: string
  readAt?: string | null
}

export interface ConversationDetail {
  uuid: string
  otherParticipant: ConversationParticipant
  messages: ConversationMessage[]
}

export interface ConversationContact {
  id: number
  name: string
  role: string
  childName?: string
}

/** Reverb `MessageSent` broadcast payload on PrivateChannel `conversation.{uuid}`. */
export interface MessageSentPayload {
  id: number
  uuid: string
  body: string
  senderId: number
  createdAt: string
}
