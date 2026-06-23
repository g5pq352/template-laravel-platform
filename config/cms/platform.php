<?php

return [
    'main_site_slug' => env('CMS_MAIN_SITE_SLUG', 'main-site'),
    'site_workspace_root' => env('CMS_SITE_WORKSPACE_ROOT', dirname(base_path())),
    'frontend_template_path' => env('CMS_FRONTEND_TEMPLATE_PATH', dirname(base_path()) . DIRECTORY_SEPARATOR . 'template-next-platform'),
    'test_frontend_url_pattern' => env('CMS_TEST_FRONTEND_URL_PATTERN', 'http://{slug}.test'),
    'test_backend_url_pattern' => env('CMS_TEST_BACKEND_URL_PATTERN', env('APP_URL', 'http://localhost')),
    'gitlab' => [
        'url' => env('CMS_GITLAB_URL', 'https://gitlab.com'),
        'token' => env('CMS_GITLAB_TOKEN'),
        'namespace_id' => env('CMS_GITLAB_NAMESPACE_ID'),
        'namespace_path' => env('CMS_GITLAB_NAMESPACE_PATH'),
        'visibility' => env('CMS_GITLAB_VISIBILITY', 'private'),
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
