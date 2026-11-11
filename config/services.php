<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\Models\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],
     'twilio' => [
         'sid' => env('TWILIO_SID'),
         'token' => env('TWILIO_TOKEN'),
         'key' => env('TWILIO_KEY'),
         'secret' => env('TWILIO_SECRET')
      ],
      'google_maps' => [
            'key' => env('GOOGLE_MAPS_API_KEY'),
        ],

    /*
    | ZeptoMail (transactional email) is plain SMTP as far as Laravel is
    | concerned — no SDK and no config array here. Its credentials live in
    | the MAIL_* environment keys (see env-snippets/zoho.txt). MAIL_STATUS
    | remains the application-level master switch for outbound mail.
    */

    'zoho_cpaas' => [
        'enabled' => (bool) env('ZOHO_CPAAS_ENABLED', false),
        'api_key' => env('ZOHO_CPAAS_API_KEY'),
        'sender_key' => env('ZOHO_CPAAS_SENDER_KEY'),
        'endpoint' => env('ZOHO_CPAAS_ENDPOINT', 'https://cpaas.zoho.com/v1/whatsapp/messages'),
        'webhook_secret' => env('ZOHO_CPAAS_WEBHOOK_SECRET'),
    ],

];
