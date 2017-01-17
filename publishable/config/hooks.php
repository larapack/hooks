<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Github Configuration
    |--------------------------------------------------------------------------
    |
    | In order to download unlimited hooks from Github and to get private
    | repositories, you must define a Github Token below. You can gather
    | your Github token at https://github.com/settings/tokens/new
    |
    */

    'github' => [
        'token' => env('HOOK_GITHUB_TOKEN'),
    ],

];
