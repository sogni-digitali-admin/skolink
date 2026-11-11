<?php

namespace App\Observers;

use App\Models\SchoolDetail;
use Illuminate\Support\Str;
use App\Models\AcademicYear;
use App\Models\School;
use Carbon\Carbon;
use Exception;
use Log;

class SchoolObserver
{
    /**
     * Handle the school "created" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function created(School $school)
    {
        //
        try
        {
            $slug = Str::slug($school->name,'-');
            $school = School::where('id',$school->id)->first(); 
            $school->slug = $slug;
            $school->save();

            $keys = ['about_us' , 'admission_open' , 'admission_close_message' , 'admission_close_on' , 'affiliation_no' , 'affiliated_by' , 'board' , 'date_of_establishment' , 'landline_no' , 'moto' , 'school_logo' , 'website' , 'school_type' , 'moe_zone'];
            foreach ($keys as $key) 
            {
                $detail = SchoolDetail::create([
                    'school_id' =>  $school->id,
                    'meta_key'  =>  $key,
                    'meta_value' => "-",
                ]);
            }

            if (AcademicYear::where('school_id', $school->id)->exists()) {
                return;
            }

            // Academic year bounds come from config('school.academic_year')
            // — Jan-Dec for Mauritius, but config-driven so Aug-Jul markets
            // keep working.
            $calendar    = config('school.academic_year');
            $currentYear = Carbon::now()->year;
            $prevYear    = $currentYear - 1;
            $crosses     = !empty($calendar['crosses_calendar_year']);

            // Previous Academic Year
            AcademicYear::create([
                'school_id'   => $school->id,
                'name'        => $crosses ? ($prevYear . '-' . $currentYear) : (string) $prevYear,
                'description' => 'Previous Academic Year',
                'start_date'  => $prevYear . '-' . $calendar['start'],
                'end_date'    => ($crosses ? $currentYear : $prevYear) . '-' . $calendar['end'],
                'status'      => 0, // OLD
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Current Academic Year
            AcademicYear::create([
                'school_id'   => $school->id,
                'name'        => $crosses ? ($currentYear . '-' . ($currentYear + 1)) : (string) $currentYear,
                'description' => 'Current Academic Year',
                'start_date'  => $currentYear . '-' . $calendar['start'],
                'end_date'    => ($crosses ? $currentYear + 1 : $currentYear) . '-' . $calendar['end'],
                'status'      => 1, // CURRENT
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
        catch(Exception $e)
        {
            Log::info($e->getMessage());
            //dd($e->getMessage());
        }
    }

    /**
     * Handle the school "updated" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function updated(School $school)
    {
        //
    }

    /**
     * Handle the school "deleted" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function deleted(School $school)
    {
        //
    }

    /**
     * Handle the school "restored" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function restored(School $school)
    {
        //
    }

    /**
     * Handle the school "force deleted" event.
     *
     * @param  \App\Models\School  $school
     * @return void
     */
    public function forceDeleted(School $school)
    {
        //
    }
}