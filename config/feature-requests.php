<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Request Workflow Configuration
    |--------------------------------------------------------------------------
    |
    | When set to true, feature requests submitted by the Wiradadi Husada
    | instansi will skip the Raffa director approval step and proceed directly
    | to the Wiradadi director after the manager approves. Set to false to
    | require the Raffa director approval for every instansi.
    |
    */

    'skip_raffa_director_for_wiradadi' => env('FEATURE_REQUEST_SKIP_RAFFA_FOR_WIRADADI', false),
];
