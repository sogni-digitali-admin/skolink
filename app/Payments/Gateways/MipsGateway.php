<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Payments\PaymentService;
use App\Payments\PaymentSession;
use App\Payments\PaymentStatusResult;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * BETA skeleton for MIPS (Mauritian aggregator wrapping cards + MCB Juice +
 * my.t money). Structurally complete but disabled until a real merchant
 * contract provides credentials and the exact payload/signature spec.
 *
 * Server-side verify() and signature-checked webhooks are the ONLY paths
 * that mark a payment paid — never the browser redirect.
 */
class MipsGateway implements PaymentGateway
{
    /**
     * Map provider statuses onto our internal states.
     *
     * @var array
     */
    const STATUS_MAP = [
        'success'   => Payment::STATUS_PAID,
        'paid'      => Payment::STATUS_PAID,
        'completed' => Payment::STATUS_PAID,
        'failed'    => Payment::STATUS_FAILED,
        'cancelled' => Payment::STATUS_FAILED,
        'declined'  => Payment::STATUS_FAILED,
        'expired'   => Payment::STATUS_EXPIRED,
        'pending'   => Payment::STATUS_PENDING,
    ];

    /** @var array */
    protected $config;

    /**
     * @param  array  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function driver()
    {
        return 'mips';
    }

    /**
     * Whether the gateway has enough credentials to talk to MIPS.
     *
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config['merchant_id'])
            && ! empty($this->config['merchant_secret'])
            && ! empty($this->baseUrl());
    }

    /**
     * Create a hosted payment session and return its redirect URL.
     *
     * @param  \App\Models\Payment  $payment
     * @return \App\Payments\PaymentSession
     */
    public function initiate(Payment $payment)
    {
        $this->guardConfigured();

        if (! $payment->provider_reference) {
            $payment->provider_reference = sprintf(
                'MIPS-%d-%d-%s',
                $payment->school_id,
                $payment->id,
                strtoupper(Str::random(8))
            );
            $payment->save();
        }

        $payload = [
            'merchant_id' => $this->config['merchant_id'],
            'reference'   => $payment->provider_reference,
            'amount'      => $payment->amount_cents,
            'currency'    => $payment->currency,
            'callback_url' => route('accountant.payments.webhook', ['driver' => 'mips']),
        ];

        $response = $this->client()
            ->withHeaders(['X-Signature' => $this->sign(json_encode($payload))])
            ->post($this->baseUrl().'/payments', $payload);

        if ($response->failed()) {
            Log::error('MIPS initiate failed', [
                'reference' => $payment->provider_reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new RuntimeException('MIPS could not start a payment session.');
        }

        $redirectUrl = $response->json('redirect_url') ?: $response->json('data.redirect_url');

        return PaymentSession::redirect($redirectUrl, ['reference' => $payment->provider_reference]);
    }

    /**
     * Server-side status lookup against MIPS. This — not the browser
     * redirect — is what the caller may use to mark a payment paid.
     *
     * @param  string  $reference
     * @return \App\Payments\PaymentStatusResult
     */
    public function verify($reference)
    {
        $this->guardConfigured();

        $response = $this->client()->get($this->baseUrl().'/payments/'.$reference);

        if ($response->failed()) {
            Log::error('MIPS verify failed', [
                'reference' => $reference,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            throw new RuntimeException('MIPS status lookup failed.');
        }

        $body = $response->json();

        return new PaymentStatusResult(
            $this->mapStatus($body['status'] ?? 'pending'),
            $body['reference'] ?? $reference,
            ! empty($body['paid_at']) ? Carbon::parse($body['paid_at']) : null,
            is_array($body) ? $body : []
        );
    }

    /**
     * Handle a signed MIPS callback. Idempotent: the raw payload is always
     * logged to payment_events, and a replay on an already-paid payment is
     * a no-op for state and ledger.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handleWebhook(Request $request)
    {
        $this->guardConfigured();
        $this->guardSignature($request);

        $reference = $request->input('reference');
        $payment   = $reference ? Payment::where('provider_reference', $reference)->first() : null;

        $result = new PaymentStatusResult(
            $this->mapStatus($request->input('status', 'pending')),
            $reference,
            $request->input('paid_at') ? Carbon::parse($request->input('paid_at')) : null,
            $request->all()
        );

        app(PaymentService::class)->applyProviderStatus($payment, $result, 'webhook');
    }

    /**
     * Throw when the gateway is not configured.
     *
     * @return void
     */
    protected function guardConfigured()
    {
        if (! ($this->config['enabled'] ?? false) || ! $this->isConfigured()) {
            throw new RuntimeException('The MIPS gateway is in beta and is disabled until configured.');
        }
    }

    /**
     * Verify the webhook HMAC signature header.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function guardSignature(Request $request)
    {
        $header    = $this->config['signature_header'] ?? 'X-MIPS-Signature';
        $signature = (string) $request->header($header, '');
        $expected  = $this->sign($request->getContent(), $this->config['webhook_secret'] ?? '');

        if ($signature === '' || $expected === '' || ! hash_equals($expected, $signature)) {
            Log::warning('MIPS webhook signature mismatch', ['reference' => $request->input('reference')]);
            abort(403, 'Invalid webhook signature.');
        }
    }

    /**
     * HMAC-SHA256 signature of a payload.
     *
     * @param  string  $payload
     * @param  string|null  $secret
     * @return string
     */
    protected function sign($payload, $secret = null)
    {
        $secret = $secret ?: ($this->config['merchant_secret'] ?? '');

        return $secret === '' ? '' : hash_hmac('sha256', $payload, $secret);
    }

    /**
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function client()
    {
        return Http::acceptJson()
            ->timeout((int) ($this->config['timeout'] ?? 15))
            ->withHeaders(['X-Merchant-Id' => $this->config['merchant_id'] ?? '']);
    }

    /**
     * @return string|null
     */
    protected function baseUrl()
    {
        $key = ($this->config['mode'] ?? 'sandbox') === 'production'
            ? 'base_url_production'
            : 'base_url_sandbox';

        $url = $this->config[$key] ?? null;

        return $url ? rtrim($url, '/') : null;
    }

    /**
     * @param  string  $status
     * @return string
     */
    protected function mapStatus($status)
    {
        return self::STATUS_MAP[strtolower((string) $status)] ?? Payment::STATUS_PENDING;
    }
}
