<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors
/**
 * Class ModularAssessmentService
 *
 * Computes PSAC results for modular (banked) subjects. Per the PSAC modular
 * approach, Science and History & Geography are examined at the end of
 * Grade 5 and again at the end of Term 2 of Grade 6; both module results
 * are banked and cumulated. The session plan and aggregation rule come from
 * the active framework preset (config/school-frameworks.php), so nothing
 * Mauritius-specific is hard-coded here.
 *
 * @package App\Services\Academic
 */
namespace App\Services\Academic;

use App\Models\GradingScale;
use App\Models\ModularAssessmentResult;
use App\Models\Standard;
use App\Models\Subject;
use InvalidArgumentException;

class ModularAssessmentService
{
    /**
     * Record (or update) one module result and flag it as banked.
     *
     * @param array $attributes Keys: school_id, academic_year_id, user_id,
     *                          subject_id, standard_id, term_sequence, score,
     *                          grading_scale_id (optional), assessed_on,
     *                          remarks (optional)
     * @return ModularAssessmentResult
     */
    public function bankResult(array $attributes)
    {
        $result = ModularAssessmentResult::updateOrCreate(
            [
                'user_id'        => $attributes['user_id'],
                'subject_id'     => $attributes['subject_id'],
                'standard_id'    => $attributes['standard_id'],
                'term_sequence'  => $attributes['term_sequence'],
            ],
            [
                'school_id'        => $attributes['school_id'],
                'academic_year_id' => $attributes['academic_year_id'],
                'score'            => $attributes['score'] ?? null,
                'grading_scale_id' => $attributes['grading_scale_id'] ?? null,
                'assessed_on'      => $attributes['assessed_on'] ?? null,
                'remarks'          => $attributes['remarks'] ?? null,
                'is_banked'        => true,
                'banked_at'        => now(),
            ]
        );

        if ($result->grading_scale_id && $result->score !== null) {
            $scale = GradingScale::find($result->grading_scale_id);
            $result->grade_label = $scale ? $scale->gradeForScore($result->score) : null;
            $result->save();
        }

        return $result;
    }

    /**
     * Compute the banked (cumulated) PSAC result for a student in one
     * modular subject across the preset's modular sessions.
     *
     * @param int $studentId users.id of the student
     * @param int $subjectId subjects.id of the modular subject
     * @return array{sessions: array, complete: bool, score: ?float, grade: ?string}
     */
    public function computeBankedResult($studentId, $subjectId)
    {
        $preset   = $this->preset();
        $sessions = $preset['modular_assessment']['sessions'];

        $subject = Subject::findOrFail($subjectId);

        $breakdown = [];
        $scores    = [];

        foreach ($sessions as $session) {
            $standard = Standard::where('school_id', $subject->school_id)
                ->where('name', $session['level'])
                ->first();

            $result = null;
            if ($standard) {
                $result = ModularAssessmentResult::where('user_id', $studentId)
                    ->where('subject_id', $subjectId)
                    ->where('standard_id', $standard->id)
                    ->where('term_sequence', $session['term'])
                    ->where('is_banked', true)
                    ->first();
            }

            $breakdown[] = [
                'level'         => $session['level'],
                'term'          => $session['term'],
                'score'         => $result ? $result->score : null,
                'grade_label'   => $result ? $result->grade_label : null,
                'banked_at'     => $result ? $result->banked_at : null,
            ];

            if ($result && $result->score !== null) {
                $scores[] = $result->score;
            }
        }

        $complete  = count($scores) === count($sessions);
        $aggregate = $preset['modular_assessment']['aggregation'] ?? 'average';
        $combined  = null;

        if (!empty($scores)) {
            switch ($aggregate) {
                case 'sum':
                    $combined = array_sum($scores);
                    break;
                case 'average':
                default:
                    $combined = round(array_sum($scores) / count($scores), 2);
                    break;
            }
        }

        return [
            'sessions' => $breakdown,
            'complete' => $complete,
            'score'    => $combined,
            'grade'    => $this->gradeForScore($combined, $subject),
        ];
    }

    /**
     * Map an aggregate score to the subject's grading scale label.
     *
     * @param float|null $score
     * @param Subject $subject
     * @return string|null
     */
    protected function gradeForScore($score, Subject $subject)
    {
        if ($score === null || !$subject->grading_scale_id) {
            return null;
        }

        $scale = GradingScale::find($subject->grading_scale_id);

        return $scale ? $scale->gradeForScore($score) : null;
    }

    /**
     * Get the active academic framework preset.
     *
     * @return array
     */
    protected function preset()
    {
        $key = config('school.preset');

        $preset = config("school-frameworks.presets.{$key}");

        if (!$preset || empty($preset['modular_assessment'])) {
            throw new InvalidArgumentException("No modular assessment configuration for preset [{$key}].");
        }

        return $preset;
    }
}
