<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class Fee
 *
 * A fee definition (one invoicable charge, e.g. "Term 1 School
 * Contribution"). Money is stored as integer cents (amount_cents); the
 * legacy `amount` attribute is exposed as major units for the old API
 * resources (App\Http\Resources\API\FeePayment reads $fee->amount).
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int|null $academic_term_id
 * @property int|null $standardLink_id Null = school-wide fee
 * @property string $name
 * @property string|null $term Legacy display label, e.g. "Term 1"
 * @property int $amount_cents
 * @property \Carbon\Carbon|null $end_date Due date
 * @property int $status
 *
 * @property-read \App\Models\School $school
 * @property-read \App\Models\AcademicYear $academicYear
 * @property-read \App\Models\AcademicTerm|null $academicTerm
 * @property-read \App\Models\StandardLink|null $standardLink
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeePayment[] $feePayments
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fees';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'academic_year_id' , 'academic_term_id' , 'standardLink_id' ,
        'name' , 'term' , 'amount_cents' , 'end_date' , 'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount_cents' => 'integer',
        'end_date'     => 'date',
    ];

    /**
     * Legacy major-unit amount (e.g. 1500.00 for Rs 1,500).
     *
     * @return float
     */
    public function getAmountAttribute()
    {
        return ((int) $this->amount_cents) / 100;
    }

    /**
     * Get the school that owns the fee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    /**
     * Get the academic year the fee belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('App\Models\AcademicYear', 'academic_year_id');
    }

    /**
     * Get the term (trimestre) the fee belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicTerm()
    {
        return $this->belongsTo('App\Models\AcademicTerm', 'academic_term_id');
    }

    /**
     * Get the class/section the fee is restricted to (null = school-wide).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function standardLink()
    {
        return $this->belongsTo('App\Models\StandardLink', 'standardLink_id');
    }

    /**
     * Get the per-student invoices generated from this fee.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function feePayments()
    {
        return $this->hasMany('App\Models\FeePayment', 'fee_id');
    }
}
