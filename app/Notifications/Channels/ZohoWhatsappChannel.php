<?php

namespace App\Notifications\Channels;

use App\Models\NotificationReceipt;
use App\Notifications\Messages\WhatsAppMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ZohoWhatsappChannel
{
    /**
     * Send the given notification via the Zoho CPaaS WhatsApp API.
     *
     * Self-skips (no exception) when the channel is disabled or
     * unconfigured, the notifiable has no WhatsApp opt-in timestamp
     * or no usable phone number, or the notification has no WhatsApp
     * representation. Throws on HTTP/transport errors so a queued
     * notification is retried.
     */
    public function send($notifiable, Notification $notification): void
    {
        $config = config('services.zoho_cpaas') ?: [];

        if (empty($config['enabled'])) {
            return;
        }

        if (empty($config['api_key']) || empty($config['sender_key']) || empty($config['endpoint'])) {
            Log::warning('ZohoWhatsappChannel: enabled but api_key, sender_key or endpoint is missing; skipping.');

            return;
        }

        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        // whatsapp_opted_in_at lives on the user profile (Phase 1); a missing
        // attribute or relation resolves to null here and means "no opt-in".
        $optedInAt = data_get($notifiable, 'whatsapp_opted_in_at')
            ?? data_get($notifiable, 'userprofile.whatsapp_opted_in_at');

        if (empty($optedInAt)) {
            return;
        }

        $phone = $this->normalizePhone(
            data_get($notifiable, 'phone')
            ?? data_get($notifiable, 'mobile_no')
            ?? data_get($notifiable, 'userprofile.alternate_no')
        );

        if ($phone === null) {
            Log::info('ZohoWhatsappChannel: no usable phone number for notifiable; skipping.', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable),
            ]);

            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (! $message instanceof WhatsAppMessage || $message->template === '') {
            return;
        }

        $payload = [
            'sender_key' => $config['sender_key'],
            'to' => $phone,
            'template_key' => $message->template,
            'variables' => $message->variables,
        ];

        if ($message->locale) {
            $payload['locale'] = $message->locale;
        }

        $receipt = $this->createReceipt($notification, $phone, $message);

        Log::info('ZohoWhatsappChannel: sending WhatsApp template message.', [
            'notification' => get_class($notification),
            'template' => $message->template,
            'to' => $phone,
        ]);

        try {
            $response = Http::withToken($config['api_key'])
                ->acceptJson()
                ->timeout(15)
                ->post($config['endpoint'], $payload);
        } catch (\Throwable $e) {
            $this->markReceiptFailed($receipt, ['error' => $e->getMessage()]);

            Log::error('ZohoWhatsappChannel: transport error.', [
                'notification' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $providerMessageId = $response->json('message_id')
            ?? $response->json('id')
            ?? data_get($response->json(), 'data.message_id');

        if ($response->failed()) {
            $this->markReceiptFailed($receipt, [
                'provider_message_id' => $providerMessageId,
                'response' => $response->json() ?: $response->body(),
            ]);

            Log::error('ZohoWhatsappChannel: Zoho CPaaS rejected the message.', [
                'notification' => get_class($notification),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        $this->markReceiptSent($receipt, $providerMessageId, $response->json());

        Log::info('ZohoWhatsappChannel: message accepted by Zoho CPaaS.', [
            'notification' => get_class($notification),
            'provider_message_id' => $providerMessageId,
        ]);
    }

    /**
     * Normalize a phone number to E.164. Bare 8-digit numbers are treated
     * as Mauritian local numbers and prefixed with +230.
     */
    protected function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 8) {
            $digits = '230'.$digits;
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return '+'.$digits;
    }

    protected function createReceipt(Notification $notification, string $phone, WhatsAppMessage $message): ?NotificationReceipt
    {
        try {
            return NotificationReceipt::create([
                'notification_id' => $notification->id ?? (string) Str::uuid(),
                'channel' => 'whatsapp',
                'provider' => NotificationReceipt::PROVIDER_ZOHO_CPAAS,
                'status' => NotificationReceipt::STATUS_QUEUED,
                'payload' => [
                    'to' => $phone,
                    'template_key' => $message->template,
                    'locale' => $message->locale,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('ZohoWhatsappChannel: could not persist notification receipt.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function markReceiptSent(?NotificationReceipt $receipt, ?string $providerMessageId, $responseBody): void
    {
        if (! $receipt) {
            return;
        }

        try {
            $receipt->update([
                'status' => NotificationReceipt::STATUS_SENT,
                'provider_message_id' => $providerMessageId,
                'payload' => array_merge($receipt->payload ?: [], ['response' => $responseBody]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ZohoWhatsappChannel: could not update notification receipt.', ['error' => $e->getMessage()]);
        }
    }

    protected function markReceiptFailed(?NotificationReceipt $receipt, array $context): void
    {
        if (! $receipt) {
            return;
        }

        try {
            $receipt->update([
                'status' => NotificationReceipt::STATUS_FAILED,
                'provider_message_id' => $context['provider_message_id'] ?? $receipt->provider_message_id,
                'payload' => array_merge($receipt->payload ?: [], $context),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ZohoWhatsappChannel: could not update notification receipt.', ['error' => $e->getMessage()]);
        }
    }
}
