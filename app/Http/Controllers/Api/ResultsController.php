<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\SiteHelper;
use App\Models\ModularAssessmentResult;
use App\Services\Academic\ModularAssessmentService;
use Illuminate\Http\Request;

class ResultsController extends Controller
{
    /**
     * PSAC-style results for one of the authenticated parent's children.
     *
     * PWA contract (pwa/src/services/types.ts PsacResult):
     *   { studentId, examName, year, subjects: [{subject, grade}], overallGrade? }
     * with grade on the PSAC core scale (1 best … 6). Banked modular
     * sessions for Science / History & Geography are cumulated through
     * ModularAssessmentService; other subjects use their latest banked
     * grade label. 404 when nothing is published yet.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $student_id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $student_id)
    {
        $children_ids = $request->user()->children->pluck('student_id')->map(function ($id) {
            return (int) $id;
        })->toArray();

        if (! in_array((int) $student_id, $children_ids)) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'This student is not linked to your account.',
            ], 403);
        }

        $school_id     = $request->user()->school_id;
        $academic_year = SiteHelper::getAcademicYear($school_id);

        $results = ModularAssessmentResult::with('subject')
            ->where('user_id', $student_id)
            ->where('is_banked', true)
            ->orderByDesc('id')
            ->get();

        if ($results->isEmpty()) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'Results not published yet.',
            ], 404);
        }

        $modular = app(ModularAssessmentService::class);

        $subjects = [];
        $grades   = [];

        foreach ($results->groupBy('subject_id') as $subjectResults) {
            $subject = $subjectResults->first()->subject;

            if (! $subject) {
                continue;
            }

            if ($subject->assessment_type === 'national_modular') {
                $label = $this->bankedGrade($modular, $student_id, $subject->id)
                    ?: $subjectResults->first()->grade_label;
            } else {
                $label = $subjectResults->first()->grade_label;
            }

            // Only numeric PSAC scale grades (1-6) fit the PWA contract.
            if (! is_numeric($label)) {
                continue;
            }

            $grade = (int) $label;

            if ($grade < 1 || $grade > 6) {
                continue;
            }

            $subjects[] = [
                'subject' => $subject->name,
                'grade'   => $grade,
            ];
            $grades[] = $grade;
        }

        if (empty($subjects)) {
            return response()->json([
                'success'   =>  false,
                'message'   =>  'Results not published yet.',
            ], 404);
        }

        $overall = (int) round(array_sum($grades) / count($grades));
        $overall = max(1, min(6, $overall));

        return response()->json([
            'success'   =>  true,
            'message'   =>  'Student Results',
            'data'      =>  [
                'studentId'    => (int) $student_id,
                'examName'     => 'PSAC',
                'year'         => $academic_year ? $academic_year->name : date('Y'),
                'subjects'     => $subjects,
                'overallGrade' => $overall,
            ],
        ], 200);
    }

    /**
     * Cumulated grade label for a modular subject (banked sessions), null
     * when the preset has no modular configuration.
     *
     * @param  \App\Services\Academic\ModularAssessmentService  $modular
     * @param  int  $student_id
     * @param  int  $subject_id
     * @return string|null
     */
    protected function bankedGrade(ModularAssessmentService $modular, $student_id, $subject_id)
    {
        try {
            return $modular->computeBankedResult($student_id, $subject_id)['grade'];
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
