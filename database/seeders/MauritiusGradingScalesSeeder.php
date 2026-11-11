<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\School;

class MauritiusGradingScalesSeeder extends Seeder
{
    /**
     * Seed the grading scales declared by the active framework preset
     * (PSAC core 1-6 and the SBA 3-level qualitative scale for Mauritius).
     *
     * @return void
     */
    public function run()
    {
        $preset = $this->preset();

        $schools = School::where('status', 1)->get();

        foreach ($schools as $school) {
            foreach ($preset['grading_scales'] as $slug => $scale) {
                $scaleId = DB::table('grading_scales')->insertGetId([
                    'school_id'   => $school->id,
                    'slug'        => $slug,
                    'name'        => $scale['name'],
                    'type'        => $scale['type'],
                    'description' => $scale['description'] ?? null,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $order = 1;
                foreach ($scale['grades'] as $grade) {
                    DB::table('grading_scale_grades')->insert([
                        'grading_scale_id' => $scaleId,
                        'label'            => $grade['label'],
                        'min_mark'         => $grade['min_mark'],
                        'max_mark'         => $grade['max_mark'],
                        'sort_order'       => $order++,
                        'description'      => $grade['description'] ?? null,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]);
                }
            }
        }
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
