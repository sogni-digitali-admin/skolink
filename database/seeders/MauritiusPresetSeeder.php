<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MauritiusPresetSeeder extends Seeder
{
    /**
     * Seed the Mauritius primary academic framework (Grades 1-6, PSAC) in
     * dependency order: academic year + terms, grading scales, levels and
     * subjects, holidays, demo school localization.
     *
     * @return void
     */
    public function run()
    {
        $this->call(MauritiusAcademicYearSeeder::class);
        $this->call(MauritiusGradingScalesSeeder::class);
        $this->call(MauritiusAcademicFrameworkSeeder::class);
        $this->call(MauritiusHolidaysSeeder::class);
        $this->call(MauritiusSchoolSeeder::class);
    }
}
