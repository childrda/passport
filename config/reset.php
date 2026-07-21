<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Workspace Domains
    |--------------------------------------------------------------------------
    |
    | Staff may sign in only from STAFF_DOMAIN. Password resets are allowed
    | only for canonical primary emails on STUDENT_DOMAIN.
    |
    */

    'staff_domain' => env('STAFF_DOMAIN', 'lcps.k12.va.us'),

    'student_domain' => env('STUDENT_DOMAIN', 'k12louisa.org'),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (teacher sign-in)
    |--------------------------------------------------------------------------
    */

    'google' => [
        'oauth' => [
            'client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),
        ],

        /*
        | Path or reference to the service-account JSON key (kept outside the repo).
        */
        'service_account_credentials' => env('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS'),

        /*
        | Workspace admin the service account impersonates via domain-wide delegation.
        */
        'impersonated_admin' => env('GOOGLE_IMPERSONATED_ADMIN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Password Generation
    |--------------------------------------------------------------------------
    |
    | Alphabet defaults exclude confusing characters: 0 O 1 l I
    |
    */

    'temp_password' => [
        'length' => (int) env('TEMP_PASSWORD_LENGTH', 10),
        'alphabet' => env(
            'TEMP_PASSWORD_ALPHABET',
            'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Classroom data driver
    |--------------------------------------------------------------------------
    |
    | mock  — fixture courses/rosters (Phase 3+)
    | google — live Classroom API (Phase 6)
    |
    */

    'classroom_driver' => env('CLASSROOM_DRIVER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Directory data driver
    |--------------------------------------------------------------------------
    |
    | mock   — fixture Directory users + mock password reset (Phase 5+)
    | google — live Admin SDK Directory API (Phase 7)
    |
    */

    'directory_driver' => env('DIRECTORY_DRIVER', 'mock'),

];
