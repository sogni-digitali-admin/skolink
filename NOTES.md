# Phase 2 NOTES — Self-hosted realtime (Reverb) + Web Push (VAPID)

Branch: `mauritius/phase-2-reverb-webpush` (base: `mauritius/phase-0-security`)

## Packages

### Added to composer.json (not yet installed — run `composer update` at integration)

| Package | Constraint | Why |
|---|---|---|
| `laravel/reverb` | `^1.13` | First-party WebSocket server for Laravel 12; speaks the Pusher protocol |
| `laravel-notification-channels/webpush` | `^13.0` | VAPID web push notifications; 13.0.1 requires `illuminate/* ^12.0` |

### Removed from composer.json

- `pusher/pusher-php-server` (root requirement; note: Reverb itself requires
  `pusher/pusher-php-server ^7.2`, so it will still be in `vendor/` as a
  transitive dependency — that is expected and correct)
- `kreait/laravel-firebase`
- `laravel-notification-channels/fcm`

`package.json` unchanged on purpose: `laravel-echo` + `pusher-js` stay — the
frontend keeps working, only its target host changed.

## Pusher / Reverb

- `config/broadcasting.php`: default connection is now `reverb`
  (`BROADCAST_CONNECTION`), with a proper `reverb` connection block reading
  `REVERB_APP_ID/KEY/SECRET/HOST/PORT/SCHEME`. The `pusher` connection block
  is gone; `redis`, `log`, `null` kept.
- `config/websockets.php` deleted — it belonged to
  `beyondcode/laravel-websockets`, which is **not installed** (not in
  composer.json); dead config.
- `resources/assets/js/bootstrap.js`: Echo still uses
  `broadcaster: "pusher"` + `pusher-js`, but now points at the self-hosted
  Reverb server via `MIX_REVERB_APP_KEY/HOST/PORT/SCHEME`
  (`wsHost/wsPort/wssPort/forceTLS`). Requires `npm run production` to rebuild
  `public/js/app.js` — the committed build artifacts still contain the old
  cloud-Pusher config until then.
- `config/filament.php`: the commented-out Echo sample now references
  `REVERB_*` env keys instead of `VITE_PUSHER_*` (comment-only change).
- `docker-compose.reverb.yml` (new, does not edit `docker-compose.yml`):
  adds a `reverb` service (same app image, `php artisan reverb:start
  --host=0.0.0.0 --port=8080`, published on 8080) and sets
  `REVERB_HOST=reverb` on the `app` service for server-to-server traffic.
  Browsers still connect to the host's `127.0.0.1:8080` (`MIX_REVERB_HOST`).
  Run: `docker compose -f docker-compose.yml -f docker-compose.reverb.yml up -d`.

## Web Push (VAPID)

- Migration `database/migrations/2026_11_11_120000_create_push_subscriptions_table.php`
  — schema matches the webpush package's published migration
  (`subscribable` morphs, `endpoint` unique 500, `public_key`, `auth_token`,
  `content_encoding`).
- `App\Models\User`: added `NotificationChannels\WebPush\HasPushSubscriptions`.
- New `App\Http\Controllers\Api\PushSubscriptionController`
  (`store`/`destroy`, run as the authenticated user).
- Routes registered inside the existing `auth:sanctum` v2 group in
  `routes/api.php` (same string-callable style as neighbours):
  - `POST /api/v2/push-subscriptions`
  - `POST /api/v2/push-subscriptions/delete` (POST used to match the file's
    conventions; browsers from Blade pages authenticate via the session
    cookie + CSRF token, which Sanctum's stateful guard accepts)
- `public/push-subscribe.js` (new): registers `/sw.js`, requests permission,
  subscribes with the VAPID public key (from `<meta name="vapid-public-key">`),
  POSTs the subscription. Included in `resources/views/layouts/app.blade.php`
  behind `@auth` — all role layouts (`admin`, `teacher`, `reception`,
  `accountant`, `student`, `library`) extend this base layout.
- `public/sw.js` (new): minimal push + notificationclick handler. Did not
  exist before; Phase 5 owns `pwa/` and can replace this with a fuller worker.

## FCM / Firebase removal

- `custompackages/brozot/laravel-fcm/` deleted (vendored package).
- `config/firebase.php` deleted (kreait config, `FIREBASE_CREDENTIALS` /
  `TEACHER_FIREBASE_CREDENTIALS`).
- `config/app.php`: removed `'FCM' => LaravelFCM\Facades\FCM::class` alias and
  the commented `LaravelFCM\FCMServiceProvider` line.
- `app/Traits/SendPushNotification.php` deleted. Every live call site already
  used `$user->notify(new SendDeviceNotification(...))`; the trait's
  `sendNotification()`/`sendTeacherNotification()` methods were only invoked
  from commented-out code. Imports/`use` statements removed from:
  `app/Console/Commands/Test/CheckPushNotification.php`,
  `app/Listeners/StandardPushEventListener.php`,
  `app/Listeners/Notification/TransportNotificationEventListener.php`,
  `app/Http/Controllers/{Admin,Teacher,Accountant}/EventsController.php`,
  `app/Http/Controllers/Admin/BirthdayController.php`.

### Notification classes rewired (both were already `ShouldQueue` on the `notification` queue)

- `App\Notifications\SendDeviceNotification` — was `FcmChannel`, now
  `WebPushChannel::class` + `'database'`.
- `App\Notifications\SendTeacherNotification` — was `FcmChannel` with a
  separate Kreait Firebase project (`appteacher`), now `WebPushChannel` +
  `'database'`.

Both keep their constructor signature `(array $data, ?string $token = null)`;
the token was the FCM `platform_token` and is now **unused** (web push routes
via the user's `push_subscriptions`). The param was made nullable because some
callers pass `platform_token` without a null check — previously a latent
TypeError. `User::routeNotificationForFcm()` removed.

### env keys

`.env.example` (and the installer's generated `.env` template in
`public/installer/includes/functions.php`): removed `PUSHER_*`,
`MIX_PUSHER_*`, `FCM_SERVER_KEY`, `FCM_SENDER_ID`, `FCM_TEACHER_SERVER_KEY`,
`FCM_TEACHER_SENDER_ID`, `FIREBASE_CREDENTIALS`, `TEACHER_FIREBASE_CREDENTIALS`,
`BROADCAST_DRIVER`; added `BROADCAST_CONNECTION=reverb`,
`REVERB_APP_ID=gegok12`, `REVERB_APP_KEY/SECRET` (empty),
`REVERB_HOST=127.0.0.1`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`,
`MIX_REVERB_*`, `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`.
A local `.env` was created from the updated `.env.example` (gitignored,
placeholder values only).

## Left in place deliberately (documented, not half-broken)

- `users.platform_token` column and all its writers (login/logout API
  controllers, admin user controllers). Mobile-app login flows still send it;
  removing the column/writes is a data/API-contract decision for integration,
  not this phase. It is simply no longer read for push.
- The phrase "Legacy FCM device token" docblocks in the two notification
  classes (intentional documentation).
- `config/services.php`, `routes/web.php`, `routes/accountant.php`,
  `app/Notifications/Channels/`, `app/Payments/`, `pwa/` — untouched per
  phase boundaries. Phase 3 owns the WhatsApp/email fallback channels; the
  push notifications here use `WebPushChannel + database` only.
- Built assets under `public/js/` still contain the old Pusher config until
  `npm run production` runs at integration.
- No legacy wrapper was needed — no FCM usage was too entangled to remove.

## Needs running at integration

1. `composer update` (installs reverb + webpush, drops firebase/fcm/pusher root pkgs)
2. `php artisan webpush:vapid` → writes `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` into `.env`
3. `php artisan migrate` (creates `push_subscriptions`)
4. Fill `REVERB_APP_KEY`/`REVERB_APP_SECRET` with random values (any string)
5. `npm run production` (rebuilds Echo config with `MIX_REVERB_*`)
6. Run Reverb: `php artisan reverb:start` (Supervisor/systemd) or
   `docker compose -f docker-compose.yml -f docker-compose.reverb.yml up -d`
7. Production: nginx proxy `/app` + `/apps` (or a `ws.` subdomain) to
   `127.0.0.1:8080`, then set `REVERB_SCHEME=https` + TLS.

## Open questions

- Phase doc mentioned `POST /api/push-subscriptions`; per the task brief the
  routes went into the existing sanctum v2 group → `/api/v2/push-subscriptions`.
  If the mobile/PWA clients expect the un-prefixed path, add an alias.
- `push-subscribe.js` asks for notification permission on first page load
  after login; if that's too aggressive, gate it behind a UI prompt later.
- Legacy mobile apps that only have FCM tokens will stop receiving push until
  they subscribe via web push — expected consequence of the FCM removal.
