<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class FeePayment
 *
 * A per-student fee assignment (invoice). Keeps the legacy column contract
 * used by App\Payments\PaymentService::syncFeeLedger: when a linked payment
 * (payments.fee_payment_id) is marked paid, this row gets paid_on set and
 * status = 1.
 *
 * status is the legacy integer flag: 0 = pending, 1 = paid, 2 = partial;
 * use the status_label accessor / STATUS_* constants instead of raw values.
 *
 * @property int $id
 * @property int $fee_id
 * @property int $user_id Student
 * @property int $school_id
 * @property int $amount_due_cents
 * @property int $amount_paid_cents
 * @property \Carbon\Carbon|null $paid_on
 * @property bool|null $notify_parent
 * @property int $status 0 pending | 1 paid | 2 partial
 * @property string|null $client_reference Idempotency key
 *
 * @property-read \App\Models\Fee $fee
 * @property-read \App\Models\User $student
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Payment[] $payments
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 0;
    const STATUS_PAID    = 1;
    const STATUS_PARTIAL = 2;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fee_payments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'fee_id' , 'user_id' , 'school_id' , 'amount_due_cents' , 'amount_paid_cents' ,
        'paid_on' , 'notify_parent' , 'status' , 'client_reference'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_due_cents'  => 'integer',
        'amount_paid_cents' => 'integer',
        'paid_on'           => 'date',
        'notify_parent'     => 'boolean',
        'status'            => 'integer',
    ];

    /**
     * String form of the legacy integer status.
     *
     * @return string
     */
    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case self::STATUS_PAID:
                return 'paid';
            case self::STATUS_PARTIAL:
                return 'partial';
            default:
                return 'pending';
        }
    }

    /**
     * Outstanding amount on this invoice, in cents.
     *
     * @return int
     */
    public function getOutstandingCentsAttribute()
    {
        return max(0, (int) $this->amount_due_cents - (int) $this->amount_paid_cents);
    }

    /**
     * Get the fee definition this invoice was generated from.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function fee()
    {
        return $this->belongsTo('App\Models\Fee', 'fee_id');
    }

    /**
     * Get the student this invoice is assigned to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    /**
     * Get the gateway payments made against this invoice.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Models\Payment', 'fee_payment_id');
    }
}
