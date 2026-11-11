<?php

namespace App\Payments\Gateways;

use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Payments\PaymentSession;
use App\Payments\PaymentStatusResult;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * Record-only gateway for the rails Mauritian schools use today:
 * cash, cheque, bank transfer and Juice by manual reference. There is no
 * remote call — the accountant records the payment and that record is the
 * source of truth, so verify() reads the local row.
 */
class ManualGateway implements PaymentGateway
{
    /** @var array */
    protected $config;

    /**
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function driver()
    {
        return 'manual';
    }

    /**
     * Record the payment: assign an internal reference, no remote session.
     *
     * @param  \App\Models\Payment  $payment
     * @return \App\Payments\PaymentSession
     */
    public function initiate(Payment $payment)
    {
        if (! $payment->provider_reference) {
            $payment->provider_reference = sprintf(
                'MAN-%d-%d-%s',
                $payment->school_id,
                $payment->id,
                strtoupper(Str::random(6))
            );
            $payment->save();
        }

        return PaymentSession::manual($payment->provider_reference, [
            'methods' => $this->config['methods'] ?? ['cash', 'cheque', 'bank_transfer', 'juice_reference'],
        ]);
    }

    /**
     * Manual payments are confirmed by the accountant's record, so the
     * local row is the truth.
     *
     * @param  string  $reference
     * @return \App\Payments\PaymentStatusResult
     */
    public function verify($reference)
    {
        $payment = Payment::where('provider_reference', $reference)->firstOrFail();

        return new PaymentStatusResult(
            $payment->status,
            $payment->provider_reference,
            $payment->paid_at
        );
    }

    /**
     * Manual rails receive no provider callbacks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function handleWebhook(Request $request)
    {
        throw new LogicException('The manual gateway does not receive webhooks.');
    }
}
