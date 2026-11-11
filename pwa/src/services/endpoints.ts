/**
 * Single source of truth for every backend endpoint the PWA consumes.
 *
 * Tags:
 *   [real]    — exists in the Laravel backend today (routes/api.php / routes/teacherapi.php)
 *   [assumed] — contract promised by a parallel modernization phase; adjust HERE only
 *               if the backend path/shape changes.
 *
 * Base URL is prepended in services/http.ts from VITE_API_BASE_URL (default '' = same origin).
 */

// ---------- Auth ----------
export const PARENT_LOGIN = '/api/parent/login' // [real] routes/api.php
export const TEACHER_LOGIN = '/api/teacher/login' // [real] routes/teacherapi.php
export const PARENT_LOGOUT = '/api/v2/logout' // [real]
export const TEACHER_LOGOUT = '/api/teacher/logout' // [real]

// ---------- Parent (Sanctum, prefix /api/v2) ----------
export const PARENT_ME = '/api/v2/myinfo' // [real]
export const SCHOOL_DETAILS = '/api/v2/school/details' // [real]
export const MY_CHILDREN = '/api/v2/my-children' // [real]
export const CHILD_DETAILS = (id: number | string) => `/api/v2/my-children/${id}/details` // [real]
export const CHILD_ATTENDANCE = (studentId: number | string) => `/api/v2/attendance/${studentId}` // [real]
export const CHILD_HOMEWORK_PENDING = (studentId: number | string) =>
  `/api/v2/homeworks/pending/${studentId}` // [real]
export const CHILD_HOMEWORK_FINISHED = (studentId: number | string) =>
  `/api/v2/homeworks/finished/${studentId}` // [real]
export const CHILD_HOMEWORK_SHOW = (studentId: number | string, id: number | string) =>
  `/api/v2/homework/show/${studentId}/${id}` // [real]
export const SCHOOL_NOTICES = '/api/v2/my-school/notices' // [real]
export const CLASS_NOTICES = (studentId: number | string) => `/api/v2/notices/${studentId}` // [real]
export const NOTICE_SHOW = (id: number | string) => `/api/v2/notice/show/${id}` // [real]
export const CHILD_LEAVES = (studentId: number | string) => `/api/v2/leaves/${studentId}` // [real]
export const LEAVE_TYPES = '/api/v2/leave/list' // [real]
export const LEAVE_ADD = (studentId: number | string) => `/api/v2/leave/add/${studentId}` // [real]
export const PARENT_NOTIFICATIONS = (studentId: number | string) =>
  `/api/v2/notifications/${studentId}` // [real]

// ---------- Parent results / fees — Phase contracts ----------
export const CHILD_RESULTS = (studentId: number | string) => `/api/v2/results/${studentId}` // [assumed: Phase 1 PSAC results]
export const CHILD_FEES = (studentId: number | string) => `/api/v2/fees/${studentId}` // [assumed: fees routes are commented out in routes/api.php today]
export const PAYMENTS = '/api/payments' // [assumed: Phase 4 payments, beta]

// ---------- Teacher (Sanctum, prefix /api/teacher) ----------
export const TEACHER_ME = '/api/teacher/myinfo' // [real]
export const TEACHER_CLASSES = '/api/teacher/subject' // [real] DashboardController@index
export const TEACHER_ATTENDANCE_LIST = '/api/teacher/attendance/list' // [real]
export const TEACHER_ATTENDANCE_ADD = '/api/teacher/attendance/add' // [real]
export const TEACHER_ABSENT_LIST = (standardLinkId: number | string, date: string, session: string) =>
  `/api/teacher/attendance/absentList/${standardLinkId}/${date}/${session}` // [real]
export const TEACHER_HOMEWORKS = '/api/teacher/homeworks' // [real]
export const TEACHER_HOMEWORK_CREATE_META = '/api/teacher/homework/add/list' // [real]
export const TEACHER_HOMEWORK_SUBJECTS = (standardLinkId: number | string) =>
  `/api/teacher/homework/subjectlist/${standardLinkId}` // [real]
export const TEACHER_HOMEWORK_ADD = '/api/teacher/homework/add' // [real]
export const TEACHER_HOMEWORK_SHOW = (id: number | string) => `/api/teacher/homework/show/${id}` // [real]
export const TEACHER_HOMEWORK_EDIT = (id: number | string) => `/api/teacher/homework/edit/${id}` // [real]
export const TEACHER_STUDENT_HOMEWORK_SHOW = (id: number | string) =>
  `/api/teacher/student/homework/show/${id}` // [real] submissions for grading
export const TEACHER_STUDENT_HOMEWORK_GRADE = (id: number | string) =>
  `/api/teacher/student/homework/edit/${id}` // [real] POST grade/remarks
export const TEACHER_CLASS_NOTICES = (teacherId: number | string) =>
  `/api/teacher/notices/class/${teacherId}` // [real]
export const TEACHER_NOTICE_ADD = '/api/teacher/notice/add' // [assumed: no teacher notice-create route exists today]

// ---------- Web Push — Phase 2 contract ----------
export const PUSH_SUBSCRIPTIONS = '/api/push-subscriptions' // [assumed: Phase 2, POST/DELETE]
export const VAPID_PUBLIC_KEY = '/api/push-subscriptions/vapid-public-key' // [assumed: returns { publicKey }]
