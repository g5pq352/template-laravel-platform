<?php

return [
    'main_site_slug' => env('CMS_MAIN_SITE_SLUG', 'main-site'),

    /*
    |--------------------------------------------------------------------------
    | Shared CMS Set Modules
    |--------------------------------------------------------------------------
    |
    | These modules are platform-level CMS definitions. Every site uses the
    | shared Set.php file from the main project, so site-specific set folders
    | cannot override them accidentally.
    |
    */
    'shared_set_modules' => [
        'keywordsInfo',
        'languageType',
        'languagePack',
        'menus',
    ],
];
