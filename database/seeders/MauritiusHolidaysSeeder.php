<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\AcademicYear;
use App\Models\School;

class MauritiusHolidaysSeeder extends Seeder
{
    /**
     * Seed Mauritian public holidays (multi-faith) from
     * config/school-holidays.php for the current academic year.
     *
     * The holiday list is config data, editable per year — lunar festival
     * dates (Eid, Divali, Cavadee...) shift and must be reviewed annually.
     *
     * @return void
     */
    public function run()
    {
        $schools = School::where('status', 1)->get();

        foreach ($schools as $school) {

            $academic_year = AcademicYear::where([
                ['school_id', $school->id],
                ['status', 1]
            ])->first();

            $year = $academic_year->start_date->format('Y');

            $holidays = config('school-holidays.' . $year, []);

            foreach ($holidays as $holiday) {
                DB::table('events')->insert([
                    'school_id'        => $school->id,
                    'academic_year_id' => $academic_year->id,
                    'select_type'      => 'school',
                    'title'            => $holiday['name'],
                    'category'         => 'holidays',
                    'start_date'       => $holiday['start'] . ' 00:00:00',
                    'end_date'         => $holiday['end'] . ' 00:00:00',
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }
    }
}
