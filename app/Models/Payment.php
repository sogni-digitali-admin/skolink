<?php

namespace App\Models;

use App\Payments\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 'pending';
    const STATUS_PAID    = 'paid';
    const STATUS_FAILED  = 'failed';
    const STATUS_EXPIRED = 'expired';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id', 'user_id', 'fee_payment_id', 'amount_cents', 'currency',
        'gateway', 'method', 'provider_reference', 'status', 'paid_at',
        'meta', 'recorded_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_cents' => 'integer',
        'meta'         => 'array',
        'paid_at'      => 'datetime',
    ];

    /**
     * Get the school that owns this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    /**
     * Get the student this payment is for.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    /**
     * Get the user (accountant) who recorded this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function recordedBy()
    {
        return $this->belongsTo('App\Models\User', 'recorded_by');
    }

    /**
     * Get the linked fee ledger entry (gegok12/fee module).
     * Only usable when that module is installed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feePayment()
    {
        return $this->belongsTo('App\Models\FeePayment', 'fee_payment_id');
    }

    /**
     * Get the gateway/webhook events for this payment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function events()
    {
        return $this->hasMany('App\Models\PaymentEvent', 'payment_id');
    }

    /**
     * Scope a query to only include paid payments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Whether this payment has reached the paid state.
     *
     * @return bool
     */
    public function isPaid()
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Human-readable amount with currency, e.g. "MUR 1,250.00".
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        return Money::format($this->amount_cents, $this->currency);
    }
}
