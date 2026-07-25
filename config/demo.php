<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo login
    |--------------------------------------------------------------------------
    |
    | The single account the seeder creates. Override in the environment for a
    | hosted demo — never commit real credentials. Because the seeder runs on
    | every boot when SEED_FRESH_ON_BOOT is on, these values are the source of
    | truth for who can log in.
    |
    */

    'user' => [
        'name' => env('DEMO_USER_NAME', 'Demo Estimator'),
        'email' => env('DEMO_USER_EMAIL', 'demo@cuttosize.test'),
        'password' => env('DEMO_USER_PASSWORD', 'password'),
    ],

    /*
    | Pre-fill the login form. Convenient locally, wrong on a public URL, so it
    | switches itself off as soon as the credentials stop being the defaults.
    */
    'prefill_login' => env('DEMO_PREFILL_LOGIN', env('DEMO_USER_PASSWORD') === null),

];
