<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'jawda_medical' => [
        'base_url' => env('JAWDA_MEDICAL_API_URL'),
    ],

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '20'),
        'admission_template' => [
            'name' => env('WHATSAPP_ADMISSION_TEMPLATE_NAME', 'admission_created'),
            'language' => env('WHATSAPP_ADMISSION_TEMPLATE_LANGUAGE', 'ar'),
        ],
        'reminder_template' => [
            'name' => env('WHATSAPP_REMINDER_TEMPLATE_NAME', 'admission_reminder'),
            'language' => env('WHATSAPP_REMINDER_TEMPLATE_LANGUAGE', 'ar'),
        ],
        'doctor_admission_template' => [
            'name' => env('WHATSAPP_DOCTOR_ADMISSION_TEMPLATE_NAME', 'admission_doctor_notice'),
            'language' => env('WHATSAPP_DOCTOR_ADMISSION_TEMPLATE_LANGUAGE', 'ar'),
        ],
    ],

];
