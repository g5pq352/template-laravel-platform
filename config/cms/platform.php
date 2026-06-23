<?php

return [
    'main_site_slug' => env('CMS_MAIN_SITE_SLUG', 'main-site'),
    'site_workspace_root' => env('CMS_SITE_WORKSPACE_ROOT', dirname(base_path())),
    'frontend_template_path' => env('CMS_FRONTEND_TEMPLATE_PATH', dirname(base_path()) . DIRECTORY_SEPARATOR . 'template-next-platform'),
    'git_repository_base_url' => env('CMS_GIT_REPOSITORY_BASE_URL'),

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
