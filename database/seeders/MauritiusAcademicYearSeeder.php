<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\School;

class MauritiusAcademicYearSeeder extends Seeder
{
    /**
     * Seed the academic year(s) and their terms from
     * config('school.academic_year'). The Mauritius academic year is the
     * calendar year (January-December) with 3 terms.
     *
     * @return void
     */
    public function run()
    {
        $calendar = config('school.academic_year');

        $currentYear = (int) date('Y');
        $nextYear    = $currentYear + 1;

        $schools = School::where('status', 1)->get();

        foreach ($schools as $school) {

            DB::table('academic_years')->insert([
                'school_id'   => $school->id,
                'name'        => $this->yearName($currentYear, $calendar),
                'description' => 'This is Current Academic Year',
                'start_date'  => $currentYear . '-' . $calendar['start'],
                'end_date'    => $currentYear . '-' . $calendar['end'],
                'status'      => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('academic_years')->insert([
                'school_id'   => $school->id,
                'name'        => $this->yearName($nextYear, $calendar),
                'description' => 'This is Next Academic Year',
                'start_date'  => $nextYear . '-' . $calendar['start'],
                'end_date'    => $nextYear . '-' . $calendar['end'],
                'status'      => 2,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            foreach ([$currentYear => 1, $nextYear => 2] as $year => $status) {
                $academicYear = DB::table('academic_years')
                    ->where('school_id', $school->id)
                    ->where('status', $status)
                    ->first();

                foreach ($calendar['terms'] as $term) {
                    DB::table('academic_terms')->insert([
                        'school_id'        => $school->id,
                        'academic_year_id' => $academicYear->id,
                        'name'             => $term['name'],
                        'sequence'         => $term['sequence'],
                        'start_date'       => $this->termDate($year, $term['start'], $calendar),
                        'end_date'         => $this->termDate($year, $term['end'], $calendar),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Build the display name for an academic year.
     *
     * @param int $year
     * @param array $calendar
     * @return string
     */
    protected function yearName($year, $calendar)
    {
        if (!empty($calendar['crosses_calendar_year'])) {
            return $year . '-' . ($year + 1);
        }

        return (string) $year;
    }

    /**
     * Resolve a term month-day to a full date. For academic years that cross
     * a calendar-year boundary (e.g. Aug-Jul), month-days before the year's
     * own start belong to the following calendar year.
     *
     * @param int $year Academic year start year
     * @param string $monthDay "MM-DD"
     * @param array $calendar
     * @return string "YYYY-MM-DD"
     */
    protected function termDate($year, $monthDay, $calendar)
    {
        if (!empty($calendar['crosses_calendar_year']) && $monthDay < $calendar['start']) {
            return ($year + 1) . '-' . $monthDay;
        }

        return $year . '-' . $monthDay;
    }
}
