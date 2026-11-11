<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class ModularAssessmentResult
 *
 * One result for one modular (banked) assessment session. For PSAC modular
 * subjects (Science, History & Geography) a pupil is assessed at the end of
 * Grade 5 and at the end of Term 2 of Grade 6; both results are banked
 * (is_banked) and cumulated by App\Services\Academic\ModularAssessmentService
 * into the final PSAC grade.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $user_id
 * @property int $subject_id
 * @property int $standard_id Grade level when assessed
 * @property int $term_sequence
 * @property float|null $score Percentage
 * @property string|null $grade_label
 * @property int|null $grading_scale_id
 * @property bool $is_banked
 * @property \Carbon\Carbon|null $banked_at
 * @property \Carbon\Carbon|null $assessed_on
 * @property string|null $remarks
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModularAssessmentResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'modular_assessment_results';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'academic_year_id' , 'user_id' , 'subject_id' , 'standard_id' ,
        'term_sequence' , 'score' , 'grade_label' , 'grading_scale_id' ,
        'is_banked' , 'banked_at' , 'assessed_on' , 'remarks'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'score'       => 'float',
        'is_banked'   => 'boolean',
        'banked_at'   => 'datetime',
        'assessed_on' => 'date',
    ];

    /**
     * Get the school for this result.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    /**
     * Get the academic year for this result.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('App\Models\AcademicYear', 'academic_year_id');
    }

    /**
     * Get the student for this result.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Models\User', 'user_id');
    }

    /**
     * Get the subject for this result.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subject()
    {
        return $this->belongsTo('App\Models\Subject', 'subject_id');
    }

    /**
     * Get the standard (grade level) at which the module was assessed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function standard()
    {
        return $this->belongsTo('App\Models\Standard', 'standard_id');
    }

    /**
     * Get the grading scale used for this result.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gradingScale()
    {
        return $this->belongsTo('App\Models\GradingScale', 'grading_scale_id');
    }
}
