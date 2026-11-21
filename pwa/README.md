# GegoK12 PWA — Parents & Teachers

Installable, offline-tolerant progressive web app for **parents and teachers only**
(primary school, ages 5–11 — there is deliberately **no student app**). It consumes
the existing Laravel 12 + Sanctum API. Notifications always target parents.

## Stack

Vue 3 + Vite 6 + TypeScript (strict) + Pinia + Vue Router + vue-i18n (EN/FR) +
vite-plugin-pwa (Workbox, injectManifest) + `idb` for the offline outbox.
No UI kit — hand-rolled mobile-first CSS with school theming via `--brand`.

## Dev / build

```bash
npm install
npm run dev        # dev server
npm run build      # type-check (vue-tsc) + production build → dist/
npm run preview    # serve the production build
npm run icons      # regenerate the placeholder PNG icons (dep-free script)
```

## Pointing at a backend

By default the app calls **same-origin** `/api/...` (serve `dist/` from the Laravel
app's web server). To target another backend, e.g. the local Docker stack:

```bash
# pwa/.env.local
VITE_API_BASE_URL=http://localhost:8090
```

Optionally set the VAPID key at build time (otherwise fetched from the API):

```bash
VITE_VAPID_PUBLIC_KEY=BN...your key
```

Realtime messaging (Laravel Reverb, pusher protocol) is configured at build time;
when the key is absent the app silently falls back to 30 s polling:

```bash
VITE_REVERB_APP_KEY=local-reverb-key
VITE_REVERB_HOST=localhost      # default: window.location.hostname
VITE_REVERB_PORT=8080           # default: 443 (https) / 80 (http)
VITE_REVERB_SCHEME=http         # default: https
```

Echo authenticates private channels against `POST /broadcasting/auth` with the
Sanctum bearer token. `laravel-echo` + `pusher-js` are code-split into a lazy
chunk that only loads when realtime is configured and a session is live.

CORS: when using a different origin, the Laravel API must allow it
(`config/cors.php`).

## API contract

Every endpoint lives in **`src/services/endpoints.ts`** — the single file to change
when a path or payload shape moves. Tags: `[real]` = exists in the backend today,
`[assumed]` = contract promised by a parallel modernization phase.

### Real (verified in `routes/api.php` / `routes/teacherapi.php`)

| Endpoint | Used for |
|---|---|
| `POST /api/parent/login` | Parent Sanctum token (body: `email`=**mobile number**, `password`, `device_id`, `device_name`) |
| `POST /api/teacher/login` | Teacher Sanctum token (same body) |
| `POST /api/v2/logout`, `POST /api/teacher/logout` | Logout |
| `GET /api/v2/myinfo`, `GET /api/teacher/myinfo` | Profile |
| `GET /api/v2/school/details` | School name / theme color (theming is best-effort) |
| `GET /api/v2/my-children` | Parent's children |
| `GET /api/v2/attendance/{student_id}` | Child attendance |
| `GET /api/v2/homeworks/pending|finished/{student_id}` | Child homework |
| `GET /api/v2/my-school/notices`, `GET /api/v2/notices/{student_id}` | Notices |
| `GET /api/v2/leaves/{student_id}`, `GET /api/v2/leave/list`, `POST /api/v2/leave/add/{student_id}` | Leave applications |
| `GET /api/teacher/subject` | Teacher's classes |
| `GET /api/teacher/attendance/list` | Attendance roster (students + absent reasons) |
| `POST /api/teacher/attendance/add` | Attendance submit — payload: `{Standardlinkid, Date, Session, AbsentList:[{student_id, reasonId, remarks}]}` (**absent-only** list; the server derives presents) |
| `GET/POST /api/teacher/homeworks…`, `GET /api/teacher/homework/add/list`, `GET /api/teacher/homework/subjectlist/{id}` | Homework assign |
| `GET /api/teacher/student/homework/show/{id}`, `POST /api/teacher/student/homework/edit/{id}` | Grade submissions |
| `GET /api/teacher/notices/class/{teacher_id}` | Teacher's notices |

### Assumed (parallel-phase contracts — backend may not exist yet)

| Endpoint | Phase | Status |
|---|---|---|
| `GET /api/v2/results/{student_id}` | Phase 1 PSAC results (grades 1–6 + Proficient/Intermediate/Basic) | **assumed** — 404 renders "not published yet" |
| `GET /api/v2/fees/{student_id}` | Fees balance (real fees routes are commented out today) | **assumed** — 404 renders "not available" |
| `POST /api/payments` | Phase 4 fee payment | **assumed, marked beta in UI** |
| `POST /api/push-subscriptions`, `DELETE /api/push-subscriptions` | Phase 2 Web Push (VAPID) | **assumed** |
| `GET /api/push-subscriptions/vapid-public-key` | VAPID key (`{publicKey}`) | **assumed** — falls back to `VITE_VAPID_PUBLIC_KEY` |
| `POST /api/teacher/notice/add` | Teacher class notice composer | **assumed** — no create route exists today; UI says so honestly on 404 |
| `GET /api/v2/conversations` | Messaging: conversation list | **assumed** (B3 contract) — offline reads fall back to the IndexedDB cache |
| `GET /api/v2/conversations/contacts` | New-chat contact picker (`childName` drives grouping) | **assumed** (B3 contract) |
| `POST /api/v2/conversations` `{user_id}` | Create/find 1:1 conversation → `{uuid}` | **assumed** (B3 contract) |
| `GET /api/v2/conversations/{uuid}` | Thread + messages | **assumed** (B3 contract) — cached per-thread offline |
| `POST /api/v2/conversations/{uuid}/messages` `{body}` | Send message | **assumed** (B3 contract) — offline sends queue in the outbox |
| `POST /api/v2/conversations/{uuid}/read` | Mark read (drives ✓✓ receipts + badges) | **assumed** (B3 contract) |
| `GET /api/v2/conversations/unread-count` | Nav badge | **assumed** (B3 contract) — polled every 30 s when Reverb is down |

Realtime: PrivateChannel `conversation.{uuid}`, event `MessageSent`
(payload `{id, uuid, body, senderId, createdAt}`) over Laravel Reverb.

PSAC grade bands (Phase 1 contract, single source: `src/utils/psac.ts`):
grades **1–2 → Proficient**, **3–4 → Intermediate**, **5–6 → Basic**.

## Offline attendance (flagship)

`src/offline/` — marks are written to IndexedDB **first** with `pending_sync`
state, then flushed to `POST /api/teacher/attendance/add`. Flush happens on
enqueue (if online), on the `online` event, via Background Sync (the SW nudges
open clients with a `SYNC_ATTENDANCE` message; the page does the POST because it
holds the token), and manually ("Retry now"). Server 4xx rejections become
`failed` with the server's message — visible and discardable, never silently
dropped. The roster is cached in IndexedDB so the sheet loads offline.

## Messaging (parents ↔ teachers)

Shared screens under `src/views/messaging/` serve both roles
(`/p/messages[/:uuid]`, `/t/messages[/:uuid]`). Transport: Reverb/Echo when
`VITE_REVERB_APP_KEY` is set and the socket connects (live `MessageSent`
updates + badge bumps), otherwise a 30 s poll of the conversations +
unread-count endpoints; mid-session socket drops fall back to polling until
the socket returns. Offline, the conversation list and open threads render
from the IndexedDB cache (`conversations`/`threads` stores, DB v2), and
composed messages queue in the **same outbox** as attendance
(`kind: 'message'`) with an honest "queued" note.

## PWA plumbing

- `manifest.webmanifest` (name, standalone, 192/512 + maskable PNGs generated by
  `scripts/generate-icons.mjs`).
- Custom service worker (`src/sw.ts`): app-shell precache + navigation fallback,
  stale-while-revalidate for `GET /api/*` (5 min TTL, 120 entries), Web Push
  display + click-through, Background Sync tag `attendance-sync`.
- Install prompt: `beforeinstallprompt` is captured at load but shown only after
  a **first value moment** (parent views attendance/results; teacher submits
  attendance). iOS gets manual Add-to-Home-Screen instructions in the profile.
- Push opt-in: friendly pre-prompt explaining *why* (absentee / fee / notice
  alerts) is shown **before** `Notification.requestPermission()`.

## Auth & roles

Sanctum bearer token in memory with `localStorage` fallback; a stable per-device
`device_id` (the backend enforces one device per account). Any 401 clears the
session and returns to `/login`. Router guard enforces `parent` → `/p/*`,
`teacher` → `/t/*`. There is no student role.

## Budget

Initial load (login route) gzipped: **~79 KB** (`index` chunk 73.0 KB + CSS 2.3 KB
+ login chunk ~1.1 KB + workbox-window 2.4 KB). Every role screen is a separate
lazy chunk; the messaging screens add ~2 KB gz each, and the realtime stack
(`laravel-echo` 8.2 KB + `pusher-js` 18.9 KB gz) loads only after login when
Reverb is configured — never on the login route. Well under the 300 KB budget.
Both bottom navs carry 6 items (Messages added with an unread-count badge).
