<?php

namespace App\Payments;

use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\StudentParentLink;
use App\Models\User;
use App\Payments\Notifications\PaymentReceiptNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class PaymentService
{
    /**
     * Record a manual payment (cash, cheque, bank transfer, Juice by
     * reference): create the pending row, initiate it on the manual
     * gateway, then mark it paid.
     *
     * @param  array  $data  school_id, user_id, amount_cents, currency?, method, fee_payment_id?, meta?
     * @param  \App\Models\User  $recordedBy
     * @return \App\Models\Payment
     */
    public function recordManualPayment(array $data, User $recordedBy)
    {
        $payment = Payment::create([
            'school_id'       => $data['school_id'],
            'user_id'         => $data['user_id'],
            'fee_payment_id'  => $data['fee_payment_id'] ?? null,
            'amount_cents'    => $data['amount_cents'],
            'currency'        => $data['currency'] ?? Support\Money::currency(),
            'gateway'         => 'manual',
            'method'          => $data['method'],
            'status'          => Payment::STATUS_PENDING,
            'meta'            => $data['meta'] ?? null,
            'recorded_by'     => $recordedBy->id,
        ]);

        app(PaymentManager::class)->forSchool($payment->school_id)->initiate($payment);

        return $this->markPaid($payment, Carbon::now(), ['source' => 'manual', 'method' => $payment->method]);
    }

    /**
     * Mark a payment paid. Idempotent: an already-paid payment is returned
     * unchanged, so replayed webhooks never double-credit the fee ledger
     * or resend receipts.
     *
     * @param  \App\Models\Payment  $payment
     * @param  \DateTimeInterface|null  $paidAt
     * @param  array  $meta
     * @return \App\Models\Payment
     */
    public function markPaid(Payment $payment, $paidAt = null, array $meta = [])
    {
        return DB::transaction(function () use ($payment, $paidAt, $meta) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isPaid()) {
                return $payment;
            }

            $payment->forceFill([
                'status'  => Payment::STATUS_PAID,
                'paid_at' => $paidAt ?: Carbon::now(),
            ])->save();

            $this->logEvent($payment, 'payment.paid', $meta);
            $this->syncFeeLedger($payment);
            $this->notifyReceipt($payment);
            $this->logActivity($payment, 'payment marked paid');

            return $payment;
        });
    }

    /**
     * Mark a payment failed (only from pending).
     *
     * @param  \App\Models\Payment  $payment
     * @param  array  $meta
     * @return \App\Models\Payment
     */
    public function markFailed(Payment $payment, array $meta = [])
    {
        return $this->transition($payment, Payment::STATUS_FAILED, 'payment.failed', $meta);
    }

    /**
     * Mark a payment expired (only from pending).
     *
     * @param  \App\Models\Payment  $payment
     * @param  array  $meta
     * @return \App\Models\Payment
     */
    public function markExpired(Payment $payment, array $meta = [])
    {
        return $this->transition($payment, Payment::STATUS_EXPIRED, 'payment.expired', $meta);
    }

    /**
     * Apply a provider-reported status (from verify() or a webhook). Every
     * call is logged to payment_events, including callbacks for unknown
     * references; state changes are idempotent.
     *
     * @param  \App\Models\Payment|null  $payment
     * @param  \App\Payments\PaymentStatusResult  $result
     * @param  string  $source  e.g. "webhook" or "verify"
     * @return \App\Models\Payment|null
     */
    public function applyProviderStatus(?Payment $payment, PaymentStatusResult $result, $source = 'webhook')
    {
        $this->logEvent($payment, $source.'.'.$result->status, $result->raw, $result->providerReference);

        if (! $payment) {
            Log::warning('Payment callback for unknown reference', [
                'reference' => $result->providerReference,
                'source'    => $source,
            ]);

            return null;
        }

        switch ($result->status) {
            case Payment::STATUS_PAID:
                return $this->markPaid($payment, $result->paidAt, $result->raw);
            case Payment::STATUS_FAILED:
                return $this->markFailed($payment, $result->raw);
            case Payment::STATUS_EXPIRED:
                return $this->markExpired($payment, $result->raw);
            default:
                return $payment;
        }
    }

    /**
     * Move a payment to a terminal non-paid state from pending only.
     *
     * @param  \App\Models\Payment  $payment
     * @param  string  $status
     * @param  string  $event
     * @param  array  $meta
     * @return \App\Models\Payment
     */
    protected function transition(Payment $payment, $status, $event, array $meta = [])
    {
        return DB::transaction(function () use ($payment, $status, $event, $meta) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== Payment::STATUS_PENDING) {
                return $payment;
            }

            $payment->forceFill(['status' => $status])->save();

            $this->logEvent($payment, $event, $meta);
            $this->logActivity($payment, 'payment '.$status);

            return $payment;
        });
    }

    /**
     * Append a row to the payment_events log.
     *
     * @param  \App\Models\Payment|null  $payment
     * @param  string  $event
     * @param  array  $payload
     * @param  string|null  $providerReference
     * @return void
     */
    protected function logEvent(?Payment $payment, $event, array $payload = [], $providerReference = null)
    {
        PaymentEvent::create([
            'school_id'          => $payment ? $payment->school_id : null,
            'payment_id'         => $payment ? $payment->id : null,
            'gateway'            => $payment ? $payment->gateway : 'unknown',
            'event'              => $event,
            'provider_reference' => $providerReference ?: ($payment ? $payment->provider_reference : null),
            'payload'            => $payload ?: null,
        ]);
    }

    /**
     * Reflect the payment on the existing fee ledger (gegok12/fee module),
     * when that module is installed and the payment is linked to it.
     *
     * @param  \App\Models\Payment  $payment
     * @return void
     */
    protected function syncFeeLedger(Payment $payment)
    {
        if (! $payment->fee_payment_id || ! Schema::hasTable('fee_payments')) {
            return;
        }

        DB::table('fee_payments')
            ->where('id', $payment->fee_payment_id)
            ->whereNull('paid_on')
            ->update([
                'paid_on'        => $payment->paid_at->toDateString(),
                'status'         => 1,
                'updated_at'     => Carbon::now(),
            ]);
    }

    /**
     * Send the receipt to the student's parents (mail + database channels).
     * Notification failures must never break payment state changes.
     *
     * @param  \App\Models\Payment  $payment
     * @return void
     */
    protected function notifyReceipt(Payment $payment)
    {
        try {
            $parentIds = StudentParentLink::where('student_id', $payment->user_id)->pluck('parent_id');
            $notifiables = User::whereIn('id', $parentIds)->get();

            if ($notifiables->isEmpty() && $payment->student) {
                $notifiables = collect([$payment->student]);
            }

            if ($notifiables->isNotEmpty()) {
                Notification::send($notifiables, new PaymentReceiptNotification($payment));
            }
        } catch (\Throwable $e) {
            Log::error('Payment receipt notification failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Write an activitylog entry (spatie/laravel-activitylog).
     *
     * @param  \App\Models\Payment  $payment
     * @param  string  $description
     * @return void
     */
    protected function logActivity(Payment $payment, $description)
    {
        if (! function_exists('activity')) {
            return;
        }

        activity()
            ->performedOn($payment)
            ->withProperties([
                'reference'    => $payment->provider_reference,
                'amount_cents' => $payment->amount_cents,
                'currency'     => $payment->currency,
                'gateway'      => $payment->gateway,
                'status'       => $payment->status,
            ])
            ->log($description);
    }
}
