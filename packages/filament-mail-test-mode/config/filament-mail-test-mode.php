<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force test mode via environment
    |--------------------------------------------------------------------------
    |
    | When enabled here, the test mode is active regardless of the setting
    | stored in the database and cannot be switched off in the panel.
    |
    */

    'enabled' => (bool) env('MAIL_TEST_MODE', false),

    'recipient' => env('MAIL_TEST_MODE_RECIPIENT'),

    /*
    |--------------------------------------------------------------------------
    | Subject prefix
    |--------------------------------------------------------------------------
    |
    | Prepended to the subject of every redirected mail. Set to null or an
    | empty string to keep the original subject.
    |
    */

    'subject_prefix' => '[TEST] ',

];
