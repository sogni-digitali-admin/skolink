<?php

namespace App\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Unique driver key, e.g. "manual", "mips".
     *
     * @return string
     */
    public function driver();

    /**
     * Start a payment and return the data needed to present it to the payer
     * (redirect URL, QR payload, or a manual record reference).
     *
     * @param  \App\Models\Payment  $payment
     * @return \App\Payments\PaymentSession
     */
    public function initiate(Payment $payment);

    /**
     * Server-side truth lookup. The ONLY trusted source for marking a
     * payment paid, together with handleWebhook(). Never trust a browser
     * redirect.
     *
     * @param  string  $reference  Provider reference of the payment
     * @return \App\Payments\PaymentStatusResult
     */
    public function verify($reference);

    /**
     * Handle a signed provider callback. Must be idempotent: replayed
     * webhooks update state without double-crediting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handleWebhook(Request $request);
}
