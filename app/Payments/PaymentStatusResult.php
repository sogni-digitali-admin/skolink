<?php

namespace App\Payments;

use App\Models\Payment;

class PaymentStatusResult
{
    /** @var string  One of Payment::STATUS_* */
    public $status;

    /** @var string|null */
    public $providerReference;

    /** @var \Carbon\CarbonInterface|null */
    public $paidAt;

    /** @var array  Raw provider payload, kept for auditing */
    public $raw;

    /**
     * @param  string  $status
     * @param  string|null  $providerReference
     * @param  \Carbon\CarbonInterface|null  $paidAt
     * @param  array  $raw
     */
    public function __construct($status, $providerReference = null, $paidAt = null, array $raw = [])
    {
        $this->status            = $status;
        $this->providerReference = $providerReference;
        $this->paidAt            = $paidAt;
        $this->raw               = $raw;
    }

    /**
     * @return bool
     */
    public function isPaid()
    {
        return $this->status === Payment::STATUS_PAID;
    }
}
