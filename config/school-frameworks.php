<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

/*
|--------------------------------------------------------------------------
| Academic framework presets
|--------------------------------------------------------------------------
|
| A "framework" is the seedable academic structure of a market: levels
| (standards), subjects with their assessment regime, grading scales and
| modular (banked) assessment rules. Everything is data — to serve another
| country, add a preset here and point config('school.preset') at it.
|
| assessment_type values:
|   national_final   — assessed once, at the national leaving exam (end G6)
|   national_modular — assessed in modules across years, results banked
|   school_based     — school-based assessment (SBA), MES-moderated
|
*/

return [

    'presets' => [

        'mauritius_primary' => [

            'label'   => 'Mauritius — Primary (Grades 1–6, PSAC)',
            'country' => 'MU',

            // Grades 1–3 form cycle 1, Grades 4–6 form cycle 2 (NYCBE).
            'levels' => [
                ['name' => 'Grade 1', 'cycle' => 'cycle_1'],
                ['name' => 'Grade 2', 'cycle' => 'cycle_1'],
                ['name' => 'Grade 3', 'cycle' => 'cycle_1'],
                ['name' => 'Grade 4', 'cycle' => 'cycle_2'],
                ['name' => 'Grade 5', 'cycle' => 'cycle_2'],
                ['name' => 'Grade 6', 'cycle' => 'cycle_2'],
            ],

            'subjects' => [
                // Assessed nationally at PSAC.
                'core' => [
                    ['name' => 'English',              'assessment_type' => 'national_final',   'grading_scale' => 'psac_core'],
                    ['name' => 'French',               'assessment_type' => 'national_final',   'grading_scale' => 'psac_core'],
                    ['name' => 'Mathematics',          'assessment_type' => 'national_final',   'grading_scale' => 'psac_core'],
                    ['name' => 'Science',              'assessment_type' => 'national_modular', 'grading_scale' => 'psac_core'],
                    ['name' => 'History & Geography',  'assessment_type' => 'national_modular', 'grading_scale' => 'psac_core'],
                ],
                // One optional language is taken per pupil (PSAC core scale).
                'optional_languages' => [
                    ['name' => 'Hindi',          'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Urdu',           'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Tamil',          'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Telugu',         'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Marathi',        'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Mandarin',       'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Arabic',         'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Kreol Morisien', 'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                    ['name' => 'Kreol Rodrige',  'assessment_type' => 'national_final', 'grading_scale' => 'psac_core'],
                ],
                // School-Based Assessment, qualitative SBA scale.
                'non_core' => [
                    ['name' => 'Communication Skills',               'assessment_type' => 'school_based', 'grading_scale' => 'sba_three_level'],
                    ['name' => 'ICT',                                'assessment_type' => 'school_based', 'grading_scale' => 'sba_three_level'],
                    ['name' => 'The Arts',                           'assessment_type' => 'school_based', 'grading_scale' => 'sba_three_level'],
                    ['name' => 'Health & Physical Education',        'assessment_type' => 'school_based', 'grading_scale' => 'sba_three_level'],
                    ['name' => 'Values & Citizenship Education',     'assessment_type' => 'school_based', 'grading_scale' => 'sba_three_level'],
                ],
            ],

            'grading_scales' => [
                // PSAC core subjects: 1 (best) … 6.
                'psac_core' => [
                    'name'        => 'PSAC Core Scale (1–6)',
                    'type'        => 'numeric',
                    'description' => 'National PSAC grading for core subjects, by percentage band.',
                    'grades'      => [
                        ['label' => '1', 'min_mark' => 75,  'max_mark' => 100],
                        ['label' => '2', 'min_mark' => 60,  'max_mark' => 74.99],
                        ['label' => '3', 'min_mark' => 50,  'max_mark' => 59.99],
                        ['label' => '4', 'min_mark' => 40,  'max_mark' => 49.99],
                        ['label' => '5', 'min_mark' => 30,  'max_mark' => 39.99],
                        ['label' => '6', 'min_mark' => 0,   'max_mark' => 29.99],
                    ],
                ],
                // Non-core SBA: qualitative 3-level competency scale.
                'sba_three_level' => [
                    'name'        => 'SBA 3-Level Scale',
                    'type'        => 'qualitative',
                    'description' => 'School-Based Assessment competency levels (MES moderated).',
                    'grades'      => [
                        ['label' => 'Proficient',   'min_mark' => null, 'max_mark' => null],
                        ['label' => 'Intermediate', 'min_mark' => null, 'max_mark' => null],
                        ['label' => 'Basic',        'min_mark' => null, 'max_mark' => null],
                    ],
                ],
            ],

            /*
             * PSAC modular approach: Science and History & Geography are
             * examined by the MES at the end of Grade 5 and again at the end
             * of Term 2 of Grade 6; both module results are banked and
             * cumulated into the final PSAC result.
             */
            'modular_assessment' => [
                'subjects' => ['Science', 'History & Geography'],
                'sessions' => [
                    ['level' => 'Grade 5', 'term' => 3],
                    ['level' => 'Grade 6', 'term' => 2],
                ],
                // How banked module scores combine (equal weighting).
                'aggregation' => 'average',
            ],
        ],
    ],
];
