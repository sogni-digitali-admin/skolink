<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

/*
|--------------------------------------------------------------------------
| School localization settings
|--------------------------------------------------------------------------
|
| Country-level defaults (currency, locale, phone, school types, academic
| calendar shape). The active academic structure itself lives in
| config/school-frameworks.php under the preset named by `preset`, so a
| different market can be served by swapping config — not code.
|
*/

return [

    /*
     * Active academic framework preset. Must be a key of
     * config/school-frameworks.php → `presets`.
     */
    'preset' => env('SCHOOL_PRESET', 'mauritius_primary'),

    'country' => env('SCHOOL_COUNTRY', 'MU'),

    'timezone' => env('TIMEZONE', 'Indian/Mauritius'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('SCHOOL_CURRENCY', 'MUR'),

    'currency_symbol' => env('SCHOOL_CURRENCY_SYMBOL', 'Rs'),

    // 'before' => "Rs 1,250.00", 'after' => "1,250.00 Rs"
    'currency_symbol_position' => env('SCHOOL_CURRENCY_SYMBOL_POSITION', 'before'),

    'currency_decimals' => (int) env('SCHOOL_CURRENCY_DECIMALS', 2),

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    */
    'locale' => [
        'default'   => env('SCHOOL_LOCALE', 'en'),
        'available' => [
            'en' => 'English',
            'fr' => 'Français',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone numbers
    |--------------------------------------------------------------------------
    |
    | Mauritius: country code +230, 8-digit national number (mobiles start
    | with 5). E.164 storage is preferred — Phase 3 (WhatsApp) depends on it.
    |
    */
    'phone' => [
        'country_code'     => env('SCHOOL_PHONE_COUNTRY_CODE', '+230'),
        'national_digits'  => (int) env('SCHOOL_PHONE_NATIONAL_DIGITS', 8),
        // Accepts a bare 8-digit national number or full E.164 (+230XXXXXXXX).
        'validation_regex' => '/^(\+230)?[0-9]{8}$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | School classification (Ministry of Education, Mauritius)
    |--------------------------------------------------------------------------
    */
    'types' => [
        'government'      => 'Government',
        'private_aided'   => 'Private (Grant-aided)',
        'private_unaided' => 'Private (Unaided)',
        'international'   => 'International',
    ],

    // MoE education zones. Optional per school (school_details meta `moe_zone`).
    'moe_zones' => [1, 2, 3, 4],

    /*
    |--------------------------------------------------------------------------
    | Academic calendar shape
    |--------------------------------------------------------------------------
    |
    | Mauritius: the academic year IS the calendar year (January–December),
    | with 3 terms. Dates below are the official 2026 term windows and are
    | used as the default template when seeding an academic year; adjust per
    | year as the Ministry publishes new calendars.
    |
    */
    'academic_year' => [
        // Month-day bounds of the academic year itself.
        'start' => '01-01',
        'end'   => '12-31',
        // false = year starts and ends within the same calendar year.
        'crosses_calendar_year' => false,
        // "Y" → "2026"; "Y-y" style spans → "2026-2027" for Aug–Jul systems.
        'name_format' => 'Y',
        'terms' => [
            ['sequence' => 1, 'name' => 'Term 1', 'start' => '01-12', 'end' => '04-03'],
            ['sequence' => 2, 'name' => 'Term 2', 'start' => '04-20', 'end' => '07-17'],
            ['sequence' => 3, 'name' => 'Term 3', 'start' => '08-17', 'end' => '10-30'],
        ],
    ],
];
