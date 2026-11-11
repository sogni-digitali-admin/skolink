<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\NotificationReceipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZohoCpaasWebhookController extends Controller
{
    /**
     * Monotonic rank per status so a late or duplicated callback can never
     * move a receipt backwards (e.g. a stale "sent" after a "read").
     */
    protected const STATUS_RANK = [
        'queued' => 1,
        'sent' => 2,
        'delivered' => 3,
        'failed' => 4,
        'read' => 5,
    ];

    /**
     * Handle Zoho CPaaS delivery-status callbacks.
     *
     * Requests are authenticated with a shared secret header and are
     * idempotent: duplicate deliveries only re-update existing
     * notification_receipts rows matched on provider_message_id.
     */
    public function store(Request $request)
    {
        $secret = config('services.zoho_cpaas.webhook_secret');

        if (empty($secret)) {
            Log::warning('ZohoCpaasWebhook: rejected, ZOHO_CPAAS_WEBHOOK_SECRET is not configured.');

            abort(403);
        }

        $provided = (string) $request->header('X-Zoho-Webhook-Secret', '');

        if ($provided === '' || ! hash_equals($secret, $provided)) {
            Log::warning('ZohoCpaasWebhook: rejected, invalid shared secret.', ['ip' => $request->ip()]);

            abort(403);
        }

        $updated = 0;

        foreach ($this->events($request) as $event) {
            $updated += $this->processEvent($event);
        }

        return response()->json(['ok' => true, 'updated' => $updated]);
    }

    /**
     * Normalize the payload to a list of status events. Zoho may deliver a
     * single status object, a top-level array, or a wrapped collection.
     */
    protected function events(Request $request): array
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return [];
        }

        foreach (['statuses', 'events', 'messages'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload = $payload[$key];
                break;
            }
        }

        if (! array_is_list($payload)) {
            $payload = [$payload];
        }

        return array_filter($payload, 'is_array');
    }

    protected function processEvent(array $event): int
    {
        $providerMessageId = $event['message_id']
            ?? $event['messageId']
            ?? $event['id']
            ?? data_get($event, 'message.id');

        $status = $this->mapStatus($event['status'] ?? data_get($event, 'message.status'));

        if (! $providerMessageId || ! $status) {
            Log::info('ZohoCpaasWebhook: skipping unrecognized status payload.', ['event' => $event]);

            return 0;
        }

        $updated = 0;

        NotificationReceipt::where('provider', NotificationReceipt::PROVIDER_ZOHO_CPAAS)
            ->where('provider_message_id', $providerMessageId)
            ->get()
            ->each(function (NotificationReceipt $receipt) use ($status, $event, &$updated) {
                $current = self::STATUS_RANK[$receipt->status] ?? 0;

                if ($current > (self::STATUS_RANK[$status] ?? 0)) {
                    return; // stale duplicate — never downgrade
                }

                $receipt->update([
                    'status' => $status,
                    'payload' => array_merge($receipt->payload ?: [], ['webhook' => $event]),
                ]);

                $updated++;
            });

        if ($updated === 0) {
            Log::info('ZohoCpaasWebhook: no matching receipt.', [
                'provider_message_id' => $providerMessageId,
                'status' => $status,
            ]);
        }

        return $updated;
    }

    protected function mapStatus(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'queued', 'accepted' => NotificationReceipt::STATUS_QUEUED,
            'sent' => NotificationReceipt::STATUS_SENT,
            'delivered' => NotificationReceipt::STATUS_DELIVERED,
            'read', 'seen' => NotificationReceipt::STATUS_READ,
            'failed', 'error', 'undelivered' => NotificationReceipt::STATUS_FAILED,
            default => null,
        };
    }
}
