<?php

namespace Database\Seeders;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;

class MauritiusFeesSeeder extends Seeder
{
    /**
     * Demo fee set for the Mauritius demo school: one school-wide
     * contribution per term (3 terms, Rs amounts) with a pending invoice
     * for every existing student. Idempotent via updateOrCreate.
     *
     * NOT wired into DatabaseSeeder — integration decides when to call it:
     *   php artisan db:seed --class=MauritiusFeesSeeder
     *
     * @return void
     */
    public function run()
    {
        // Rs 1,500.00 / Rs 1,250.00 / Rs 1,000.00 per term, in cents.
        $amounts = [1 => 150000, 2 => 125000, 3 => 100000];

        $schools = School::where('status', 1)->get();

        foreach ($schools as $school) {
            $academicYear = AcademicYear::where('school_id', $school->id)
                ->where('status', 1)
                ->first();

            if (! $academicYear) {
                continue;
            }

            $terms = AcademicTerm::where('academic_year_id', $academicYear->id)
                ->orderBy('sequence')
                ->get();

            $students = User::where('usergroup_id', User::STUDENT_USERGROUP_ID)
                ->where('school_id', $school->id)
                ->get();

            foreach ($terms as $term) {
                $fee = Fee::updateOrCreate(
                    [
                        'school_id'        => $school->id,
                        'academic_year_id' => $academicYear->id,
                        'academic_term_id' => $term->id,
                        'name'             => 'School Contribution — '.$term->name,
                    ],
                    [
                        'term'         => $term->name,
                        'amount_cents' => $amounts[$term->sequence] ?? 150000,
                        'end_date'     => $term->end_date,
                        'status'       => 1,
                    ]
                );

                foreach ($students as $student) {
                    FeePayment::updateOrCreate(
                        [
                            'fee_id'  => $fee->id,
                            'user_id' => $student->id,
                        ],
                        [
                            'school_id'         => $school->id,
                            'amount_due_cents'  => $fee->amount_cents,
                            'amount_paid_cents' => 0,
                            'status'            => FeePayment::STATUS_PENDING,
                            'notify_parent'     => true,
                        ]
                    );
                }
            }
        }
    }
}
