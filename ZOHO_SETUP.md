# Zoho Channels Setup — ZeptoMail (email) + Zoho CPaaS (WhatsApp)

Both channels terminate at one vendor on one credit balance, pay-as-you-go,
no monthly lock-in. There is intentionally **no SMS channel**.

Env keys for both channels are collected in `env-snippets/zoho.txt` — merge
them into `.env` / `.env.example` with real values.

---

## 1. ZeptoMail — transactional email (config only, no code)

GegoK12 already uses Laravel's SMTP mailer, so ZeptoMail is purely a
console + env change.

### Console steps

1. Create a **Mail Agent** in the ZeptoMail console.
2. Add and verify the school domain: publish the **SPF**, **DKIM** and
   **CNAME** records ZeptoMail shows you at the DNS provider and wait for
   verification.
3. Generate a **Send Mail Token** for the Mail Agent.
4. Enable **bounce tracking**; review bounces after the first bulk send —
   bad addresses in imported student data are the usual deliverability
   killer.

### Env mapping

The Send Mail Token becomes `MAIL_PASSWORD`; `MAIL_USERNAME` is literally
`emailapikey`. Full block in `env-snippets/zoho.txt` (`MAIL_HOST`,
`MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`,
`MAIL_STATUS=on`).

### Test

```bash
php artisan tinker
>>> Mail::raw('ok', fn($m) => $m->to('you@example.com')->subject('ZeptoMail OK'));
```

---

## 2. Zoho CPaaS — WhatsApp Business (custom notification channel)

Zoho CPaaS is a Meta BSP: it handles WABA/number setup and template
submission; the app sends with one POST (sender key + recipient + template
key + variables). Code: `app/Notifications/Channels/ZohoWhatsappChannel.php`.

### Console steps (operator)

1. Register the school's **WhatsApp Business number** — a Mauritian **+230**
   number dedicated to the school.
2. Submit message **templates in EN and FR**: absentee alert, fee reminder,
   school closure, meeting notice. Note the approval turnaround; template
   keys must match the `template` values passed by `toWhatsApp()` methods.
3. Confirm the **per-message rate for Mauritius** and enable **delivery
   webhooks**.
4. Create **API credentials** (bearer token) → `ZOHO_CPAAS_API_KEY`, and
   copy the **sender key** of the registered number → `ZOHO_CPAAS_SENDER_KEY`.
5. Point the delivery webhook at
   `POST https://<app-domain>/webhooks/zoho-cpaas` and configure it to send
   the shared secret as the `X-Zoho-Webhook-Secret` header. Generate the
   secret with `openssl rand -hex 32` → `ZOHO_CPAAS_WEBHOOK_SECRET`.
6. Only then set `ZOHO_CPAAS_ENABLED=true`.

### Runtime behaviour

- The channel **self-skips** (no error) when disabled, unconfigured, the
  recipient has no `whatsapp_opted_in_at` opt-in timestamp (DPA + Meta
  policy), or has no usable phone number.
- Phone numbers are normalized to E.164; bare 8-digit numbers are treated
  as Mauritian local numbers and prefixed with `+230`.
- HTTP/transport errors are thrown so the queued notification is retried.
- Every attempt is logged and persisted to `notification_receipts`;
  delivery webhooks update those rows idempotently (status can never move
  backwards, e.g. a stale `sent` after a `read`).
- Webhook traffic is stateless: `routes/webhooks.php` is loaded under the
  `api` middleware group (no session, no CSRF) from `bootstrap/app.php`.

### Channel ladder

Notifications declare channels in priority order; each self-skips:

```php
public function via($notifiable)
{
    return [WebPushChannel::class, ZohoWhatsappChannel::class, 'mail', 'database'];
}
```

Reserve WhatsApp for high-value events (absentee, fee overdue, emergency
closure) — Meta prices business-initiated template messages per
conversation; push and email carry the routine volume for free.
