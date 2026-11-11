<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\School;

class MauritiusAcademicFrameworkSeeder extends Seeder
{
    /**
     * Seed the levels (standards) and subjects of the active framework
     * preset — for mauritius_primary: Grades 1-6 tagged with their cycle,
     * NCF core subjects (with optional languages as electives) and non-core
     * school-based subjects, each carrying its assessment_type and grading
     * scale. Everything is read from config/school-frameworks.php.
     *
     * Requires: schools, sections and grading scales already seeded.
     *
     * @return void
     */
    public function run()
    {
        $preset = $this->preset();

        $schools = School::where('status', 1)->get();

        foreach ($schools as $school) {

            // Levels (standards) — Grade 1-6 with cycle tags.
            $order = 1;
            foreach ($preset['levels'] as $level) {
                DB::table('standards')->insert([
                    'school_id'  => $school->id,
                    'name'       => $level['name'],
                    'slug'       => Str::slug($level['name']) . '-' . $school->id,
                    'order'      => $order++,
                    'cycle'      => $level['cycle'] ?? null,
                    'status'     => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $academicYear = AcademicYear::where([['school_id', $school->id], ['status', 1]])->first();
            $standards    = DB::table('standards')->where('school_id', $school->id)->get();
            $sections     = Section::where('school_id', $school->id)->get();

            $scales = DB::table('grading_scales')->where('school_id', $school->id)->pluck('id', 'slug');

            foreach ($standards as $standard) {
                foreach ($sections as $section) {

                    foreach ($preset['subjects']['core'] as $subject) {
                        $this->insertSubject($school, $academicYear, $standard, $section, $subject, [
                            'type'    => 'core',
                            'is_core' => true,
                        ], $scales);
                    }

                    foreach ($preset['subjects']['non_core'] as $subject) {
                        $this->insertSubject($school, $academicYear, $standard, $section, $subject, [
                            'type'    => 'core',
                            'is_core' => false,
                        ], $scales);
                    }

                    // One optional language per pupil (PSAC core scale).
                    foreach ($preset['subjects']['optional_languages'] as $language) {
                        $this->insertSubject($school, $academicYear, $standard, $section, $language, [
                            'type'    => 'elective',
                            'is_core' => true,
                        ], $scales);
                    }
                }
            }
        }
    }

    /**
     * Insert one subject row for a standard/section pair.
     *
     * @param \App\Models\School $school
     * @param \App\Models\AcademicYear $academicYear
     * @param object $standard
     * @param \App\Models\Section $section
     * @param array $subject Preset subject definition
     * @param array $overrides Extra column values
     * @param \Illuminate\Support\Collection $scales Grading scale ids by slug
     * @return void
     */
    protected function insertSubject($school, $academicYear, $standard, $section, $subject, $overrides, $scales)
    {
        DB::table('subjects')->insert([
            'school_id'        => $school->id,
            'academic_year_id' => $academicYear->id,
            'standard_id'      => $standard->id,
            'section_id'       => $section->id,
            'name'             => $subject['name'],
            'code'             => strtoupper(substr(Str::slug($subject['name'], ''), 0, 3)) . '-' . $section->name . '-' . $standard->name,
            'type'             => $overrides['type'],
            'is_core'          => $overrides['is_core'],
            'assessment_type'  => $subject['assessment_type'] ?? null,
            'grading_scale_id' => isset($subject['grading_scale']) ? ($scales[$subject['grading_scale']] ?? null) : null,
            'status'           => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /**
     * Get the active framework preset.
     *
     * @return array
     */
    protected function preset()
    {
        return config('school-frameworks.presets.' . config('school.preset'));
    }
}
