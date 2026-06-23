<?php

return [
    'main_site_slug' => env('CMS_MAIN_SITE_SLUG', 'main-site'),
    'site_workspace_root' => dirname(base_path()),
    'frontend_template_path' => dirname(base_path()) . DIRECTORY_SEPARATOR . 'template-next-platform',
    'test_frontend_url_pattern' => 'http://{slug}.test',
    'test_backend_url_pattern' => rtrim(env('APP_URL', 'http://localhost'), '/'),
    'site_database_auto_provision' => true,
    'site_database_template_path' => database_path('templates/site.sql'),
    'gitlab' => [
        'url' => env('CMS_GITLAB_URL', 'https://gitlab.com'),
        'token' => env('CMS_GITLAB_TOKEN'),
        'namespace_path' => env('CMS_GITLAB_NAMESPACE_PATH'),
        'visibility' => 'private',
    ],

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
