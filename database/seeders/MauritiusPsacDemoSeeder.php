<?php

namespace Database\Seeders;

use App\Helpers\SiteHelper;
use App\Models\School;
use App\Models\Standard;
use App\Models\StudentAcademic;
use App\Models\Subject;
use App\Services\Academic\ModularAssessmentService;
use Illuminate\Database\Seeder;

/**
 * Demo PSAC results so /api/v2/results/{id} and the PWA Results screen
 * have real data: banked modular sessions (Science, History & Geography)
 * and final core subjects (English, French, Mathematics).
 *
 * Idempotent: ModularAssessmentService::bankResult uses updateOrCreate.
 */
class MauritiusPsacDemoSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::first();
        if (! $school) {
            return;
        }

        $academicYear = SiteHelper::getAcademicYear($school->id);
        $service      = app(ModularAssessmentService::class);

        $grade5 = Standard::where('name', 'Grade 5')->first();
        $grade6 = Standard::where('name', 'Grade 6')->first();

        $science  = Subject::where('name', 'Science')->first();
        $histGeo  = Subject::where('name', 'History & Geography')->first();
        $finals   = collect(['English', 'French', 'Mathematics'])
            ->map(fn ($name) => Subject::where('name', $name)->first())
            ->filter();

        if (! $grade5 || ! $grade6 || ! $science || ! $histGeo) {
            return;
        }

        $bank = function ($studentId, $subject, $standard, int $termSeq, int $score, string $date) use ($service, $school, $academicYear) {
            $service->bankResult([
                'user_id'          => $studentId,
                'subject_id'       => $subject->id,
                'standard_id'      => $standard->id,
                'term_sequence'    => $termSeq,
                'school_id'        => $school->id,
                'academic_year_id' => $academicYear->id,
                'score'            => $score,
                'grading_scale_id' => $subject->grading_scale_id,
                'assessed_on'      => $date,
            ]);
        };

        // Grade 6 students: full PSAC profile
        // (modular subjects banked on both sessions + final core subjects)
        $g6Students = StudentAcademic::whereHas('standardLink', function ($q) use ($grade6) {
            $q->where('standard_id', $grade6->id);
        })->limit(8)->pluck('user_id');

        foreach ($g6Students as $i => $studentId) {
            // Session 1: end of Grade 5 (Term 3 of previous year, banked)
            $bank($studentId, $science, $grade5, 3, rand(35, 90), '2025-10-30');
            $bank($studentId, $histGeo, $grade5, 3, rand(35, 90), '2025-10-30');
            // Session 2: Grade 6, end of Term 2
            $bank($studentId, $science, $grade6, 2, rand(40, 95), '2026-07-17');
            $bank($studentId, $histGeo, $grade6, 2, rand(40, 95), '2026-07-17');
            // Finals: end of Grade 6
            foreach ($finals as $subject) {
                $bank($studentId, $subject, $grade6, 3, rand(30, 95), '2026-10-30');
            }
        }

        // Grade 5 students: first modular session only (mid-course profile)
        $g5Students = StudentAcademic::whereHas('standardLink', function ($q) use ($grade5) {
            $q->where('standard_id', $grade5->id);
        })->limit(6)->pluck('user_id');

        foreach ($g5Students as $studentId) {
            $bank($studentId, $science, $grade5, 3, rand(35, 90), '2026-10-30');
            $bank($studentId, $histGeo, $grade5, 3, rand(35, 90), '2026-10-30');
        }
    }
}
