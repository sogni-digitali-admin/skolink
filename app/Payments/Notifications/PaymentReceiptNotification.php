<?php

namespace App\Payments\Notifications;

use App\Models\Payment;
use App\Payments\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceiptNotification extends Notification
{
    use Queueable;

    /** @var \App\Models\Payment */
    public $payment;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Payment  $payment
     * @return void
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Get the notification's delivery channels. Phase 3 extends the
     * delivery ladder (e.g. WhatsApp) separately.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $payment = $this->payment;
        $school  = $payment->school;

        return (new MailMessage)
            ->subject('Fee payment receipt '.$payment->provider_reference)
            ->greeting('Payment received')
            ->line(($school ? $school->name.' has' : 'We have').' received a fee payment of '
                .$payment->formatted_amount
                .($payment->student ? ' for '.$payment->student->full_name : '').'.')
            ->line('Reference: '.$payment->provider_reference)
            ->line('Paid at: '.optional($payment->paid_at)->format('d M Y H:i'))
            ->line('Thank you.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'type'               => 'payment_receipt',
            'payment_id'         => $this->payment->id,
            'reference'          => $this->payment->provider_reference,
            'amount_cents'       => $this->payment->amount_cents,
            'currency'           => $this->payment->currency,
            'formatted_amount'   => Money::format($this->payment->amount_cents, $this->payment->currency),
            'student_id'         => $this->payment->user_id,
            'paid_at'            => optional($this->payment->paid_at)->toDateTimeString(),
        ];
    }
}
