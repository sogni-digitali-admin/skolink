<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;
use App\Models\School;

class MauritiusSchoolSeeder extends Seeder
{
    /**
     * Localize the seeded demo school: Mauritian identity details, school
     * type and MoE zone meta (school_details), and opens the admission form
     * so the DPA consent checkboxes can be exercised on a fresh install.
     *
     * @return void
     */
    public function run()
    {
        $school = School::where('status', 1)->first();

        if (!$school) {
            return;
        }

        DB::table('schools')->where('id', $school->id)->update([
            'name'       => 'Demo Government Primary School',
            'phone'      => '+2304130000',
            'updated_at' => now(),
        ]);

        $meta = [
            'school_type' => 'government',   // government | private_aided | private_unaided | international
            'moe_zone'    => '1',            // MoE education zone 1-4
            'board'       => 'Mauritius Examinations Syndicate (MES)',
            'admission_open' => '1',
        ];

        foreach ($meta as $key => $value) {
            DB::table('school_details')->updateOrInsert(
                ['school_id' => $school->id, 'meta_key' => $key],
                ['meta_value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
