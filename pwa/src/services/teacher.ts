import { api } from './http'
import * as EP from './endpoints'
import type {
  AbsentReason,
  AttendanceRoster,
  AttendanceSubmitPayload,
  HomeworkItem,
  NoticeItem,
  RosterStudent,
  SubmissionItem,
  TeacherClass,
} from './types'

function asArray(raw: unknown): unknown[] {
  if (Array.isArray(raw)) return raw
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>
    if (Array.isArray(obj.data)) return obj.data
  }
  return []
}

function pickStr(obj: Record<string, unknown>, keys: string[]): string | undefined {
  for (const k of keys) {
    const v = obj[k]
    if (typeof v === 'string' && v) return v
    if (typeof v === 'number') return String(v)
  }
  return undefined
}

function mapClass(raw: unknown): TeacherClass {
  const o = (raw ?? {}) as Record<string, unknown>
  return {
    standardLinkId: Number(o.standardLink_id ?? o.standard_link_id ?? o.id ?? 0),
    className: pickStr(o, ['class_name', 'standard', 'name', 'className']) ?? 'Class',
    section: pickStr(o, ['section', 'section_name']),
    subjectName: pickStr(o, ['subject_name', 'subject']),
  }
}

function mapStudent(raw: unknown): RosterStudent {
  const o = (raw ?? {}) as Record<string, unknown>
  const user = (o.user ?? {}) as Record<string, unknown>
  const profile = (user.userprofile ?? user.profile ?? {}) as Record<string, unknown>
  const first = pickStr(profile, ['firstname', 'first_name']) ?? pickStr(user, ['name']) ?? ''
  const last = pickStr(profile, ['lastname', 'last_name']) ?? ''
  return {
    id: Number(user.id ?? o.user_id ?? o.id ?? 0),
    name: `${first} ${last}`.trim() || `#${o.user_id ?? o.id ?? '?'}`,
    rollNo: (o.roll_no as string | number | undefined) ?? undefined,
    avatar: pickStr(profile, ['avatar', 'photo']),
  }
}

/** Teacher's classes/subjects (DashboardController@index). */
export async function getTeacherClasses(): Promise<TeacherClass[]> {
  const raw = await api<unknown>(EP.TEACHER_CLASSES)
  const arr = asArray(raw)
  if (arr.length) return arr.map(mapClass)
  // DashboardController returns a keyed object; try common keys.
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>
    for (const key of ['standardlist', 'subjects', 'data', 'classes']) {
      if (Array.isArray(obj[key])) return (obj[key] as unknown[]).map(mapClass)
    }
  }
  return []
}

/**
 * Roster for the attendance sheet (AttendanceController@index):
 * returns { standardlist, studentlist, absentReasonlist }.
 */
export async function getAttendanceRoster(): Promise<AttendanceRoster> {
  const raw = (await api<unknown>(EP.TEACHER_ATTENDANCE_LIST)) as Record<string, unknown> | null
  const obj = raw ?? {}
  return {
    classes: asArray(obj.standardlist).map(mapClass),
    students: asArray(obj.studentlist).map(mapStudent),
    absentReasons: asArray(obj.absentReasonlist).map((r) => {
      const o = (r ?? {}) as Record<string, unknown>
      return { id: Number(o.id ?? 0), name: pickStr(o, ['reason', 'name', 'title']) ?? '' } as AbsentReason
    }),
  }
}

/** Submit attendance (absent list) — goes through the offline outbox in offline/outbox.ts. */
export async function submitAttendance(payload: AttendanceSubmitPayload): Promise<void> {
  await api(EP.TEACHER_ATTENDANCE_ADD, { method: 'POST', body: payload })
}

export async function getTeacherHomeworks(): Promise<HomeworkItem[]> {
  const raw = await api<unknown>(EP.TEACHER_HOMEWORKS)
  return asArray(raw) as HomeworkItem[]
}

export async function getHomeworkCreateMeta(): Promise<{ classes: TeacherClass[] }> {
  const raw = await api<unknown>(EP.TEACHER_HOMEWORK_CREATE_META)
  if (raw && typeof raw === 'object') {
    const obj = raw as Record<string, unknown>
    for (const key of ['standardlist', 'classes', 'data']) {
      if (Array.isArray(obj[key])) {
        return { classes: (obj[key] as unknown[]).map(mapClass) }
      }
    }
  }
  return { classes: [] }
}

export async function getHomeworkSubjects(standardLinkId: number): Promise<string[]> {
  const raw = await api<unknown>(EP.TEACHER_HOMEWORK_SUBJECTS(standardLinkId))
  return asArray(raw).map((s) => {
    if (typeof s === 'string') return s
    const o = (s ?? {}) as Record<string, unknown>
    return pickStr(o, ['name', 'subject_name', 'title']) ?? ''
  })
}

export interface HomeworkCreateInput {
  standardLinkId: number
  subject: string
  title: string
  description: string
  dueDate: string
}

export async function createHomework(input: HomeworkCreateInput): Promise<void> {
  await api(EP.TEACHER_HOMEWORK_ADD, {
    method: 'POST',
    body: {
      standardLink_id: input.standardLinkId,
      subject: input.subject,
      title: input.title,
      description: input.description,
      submission_date: input.dueDate,
    },
  })
}

/** Submissions for one homework (StudentHomeworkController@show). */
export async function getHomeworkSubmissions(homeworkId: number): Promise<SubmissionItem[]> {
  const raw = await api<unknown>(EP.TEACHER_STUDENT_HOMEWORK_SHOW(homeworkId))
  const arr = asArray(raw)
  return arr.map((s) => {
    const o = (s ?? {}) as Record<string, unknown>
    const student = (o.student ?? o.user ?? {}) as Record<string, unknown>
    const profile = (student.userprofile ?? {}) as Record<string, unknown>
    const first = pickStr(profile, ['firstname', 'first_name']) ?? pickStr(student, ['name']) ?? ''
    const last = pickStr(profile, ['lastname', 'last_name']) ?? ''
    return {
      id: Number(o.id ?? 0),
      studentName: `${first} ${last}`.trim() || pickStr(o, ['student_name']) || '—',
      submittedAt: pickStr(o, ['submitted_at', 'created_at']),
      answer: pickStr(o, ['answer', 'comments', 'description']),
      grade: (o.marks ?? o.grade ?? null) as string | number | null,
      remarks: (o.remarks ?? null) as string | null,
    }
  })
}

/** Grade one submission (StudentHomeworkController@update). */
export async function gradeSubmission(
  submissionId: number,
  grade: string,
  remarks: string,
): Promise<void> {
  await api(EP.TEACHER_STUDENT_HOMEWORK_GRADE(submissionId), {
    method: 'POST',
    body: { marks: grade, remarks },
  })
}

export async function getTeacherClassNotices(teacherId: number): Promise<NoticeItem[]> {
  const raw = await api<unknown>(EP.TEACHER_CLASS_NOTICES(teacherId))
  return asArray(raw) as NoticeItem[]
}

/** [assumed] No teacher notice-create route exists in the backend today. */
export async function createClassNotice(input: {
  standardLinkId: number
  title: string
  body: string
}): Promise<void> {
  await api(EP.TEACHER_NOTICE_ADD, {
    method: 'POST',
    body: {
      standardLink_id: input.standardLinkId,
      title: input.title,
      description: input.body,
    },
  })
}
