<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class GradingScale
 *
 * A named grading scale (e.g. "PSAC Core Scale (1-6)", "SBA 3-Level Scale")
 * that subjects and exam results can reference. Scales are seeded from the
 * active academic framework preset (config/school-frameworks.php).
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $slug
 * @property string $name
 * @property string $type numeric|qualitative
 * @property string|null $description
 *
 * @property-read \App\Models\School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\GradingScaleGrade[] $grades
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Subject[] $subjects
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScale extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grading_scales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'slug' , 'name' , 'type' , 'description'
    ];

    /**
     * Get the school that owns the grading scale.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School', 'school_id');
    }

    /**
     * Get the grades of the scale, ordered best-first.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function grades()
    {
        return $this->hasMany('App\Models\GradingScaleGrade', 'grading_scale_id')->orderBy('sort_order');
    }

    /**
     * Get the subjects using this grading scale.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subjects()
    {
        return $this->hasMany('App\Models\Subject', 'grading_scale_id');
    }

    /**
     * Resolve the grade label for a percentage score on a numeric scale.
     *
     * @param float $score Percentage (0-100)
     * @return string|null Null for qualitative scales or out-of-band scores
     */
    public function gradeForScore($score)
    {
        if ($this->type !== 'numeric') {
            return null;
        }

        $grade = $this->grades()
            ->whereNotNull('min_mark')
            ->where('min_mark', '<=', $score)
            ->where('max_mark', '>=', $score)
            ->orderBy('sort_order')
            ->first();

        return $grade ? $grade->label : null;
    }
}
