<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class AcademicTerm
 *
 * A term within an academic year (Mauritius: 3 terms, January-December).
 * Term windows come from config('school.academic_year.terms') at seed time
 * and are editable per year.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property int $sequence
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon $end_date
 *
 * @property-read \App\Models\School $school
 * @property-read \App\Models\AcademicYear $academicYear
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicTerm extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'academic_terms';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'academic_year_id' , 'name' , 'sequence' , 'start_date' , 'end_date'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Get the school that owns the term.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    /**
     * Get the academic year the term belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('App\Models\AcademicYear', 'academic_year_id');
    }
}
