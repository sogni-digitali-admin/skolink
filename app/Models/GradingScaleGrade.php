<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class GradingScaleGrade
 *
 * One grade band within a GradingScale. Numeric bands carry a percentage
 * range (min_mark/max_mark); qualitative bands (Proficient, Intermediate,
 * Basic) carry only a label and sort order.
 *
 * @property int $id
 * @property int $grading_scale_id
 * @property string $label
 * @property float|null $min_mark
 * @property float|null $max_mark
 * @property int $sort_order
 * @property string|null $description
 *
 * @property-read \App\Models\GradingScale $gradingScale
 *
 * @mixin \Eloquent
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradingScaleGrade extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grading_scale_grades';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'grading_scale_id' , 'label' , 'min_mark' , 'max_mark' , 'sort_order' , 'description'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'min_mark' => 'float',
        'max_mark' => 'float',
    ];

    /**
     * Get the grading scale this grade belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gradingScale()
    {
        return $this->belongsTo('App\Models\GradingScale', 'grading_scale_id');
    }
}
