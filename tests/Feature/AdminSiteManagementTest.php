<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Site;
use App\Models\Tenant;
use App\Support\CmsSetLoader;
use App\Support\SiteDeploymentManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdminSiteManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_open_site_management_page(): void
    {
        [$admin] = $this->adminAndTenant();

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->get(route('admin.sites.index'))
            ->assertOk()
            ->assertSee('多站管理')
            ->assertSee('admin-site-switcher', false);
    }

    public function test_admin_can_create_site_with_domains(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'test-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $domain = $slug . '.example.test';
        $setPath = storage_path('framework/testing/site-create-set-' . $slug);

        $response = $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Test Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'api_allowed_origins' => "https://frontend.example.test\nhttps://www.example.test",
                'api_access_token' => 'front-server-token',
                'cms_set_path' => $setPath,
                'repository_path' => 'D:\wamp64\www\test-site',
                'db_connection' => 'test_site',
                'db_host' => '127.0.0.1',
                'db_port' => '3307',
                'db_database' => 'test_site_db',
                'db_username' => 'test_user',
                'db_password' => 'secret',
                'enabled_modules' => ['news', 'products'],
                'custom_modules' => [
                    ['name' => 'Blog', 'slug' => 'blog', 'type' => 'single'],
                    ['name' => 'Events', 'slug' => 'events', 'type' => 'multi'],
                ],
                'production_domain' => $domain,
                'frontend_url' => 'https://' . $domain,
                'admin_url' => 'https://' . $domain . '/cms',
                'git_repository_url' => 'https://github.com/example/test-site.git',
                'initialized_at' => '2026-06-22 10:00:00',
                'domain_bound_at' => '2026-06-22 11:00:00',
                'deployment_notes' => 'Custom site notes.',
                'primary_domain_index' => '1',
                'domains' => [
                    ['domain' => 'https://' . $slug . '.secondary.test', 'force_https' => '0'],
                    ['domain' => $domain, 'force_https' => '1'],
                ],
            ]);

        $site = Site::query()->where('slug', $slug)->first();

        $response->assertRedirect($site ? route('admin.sites.edit', $site) : route('admin.sites.index'));
        $this->assertNotNull($site);
        $this->assertSame(['https://frontend.example.test', 'https://www.example.test'], $site->settings['api_allowed_origins']);
        $this->assertSame('front-server-token', $site->settings['api_access_token']);
        $this->assertSame($setPath, $site->settings['cms_set_path']);
        $this->assertSame('D:\wamp64\www\test-site', $site->settings['repository_path']);
        $this->assertSame([
            'connection' => 'test_site',
            'host' => '127.0.0.1',
            'port' => '3307',
            'database' => 'test_site_db',
            'username' => 'test_user',
            'password' => 'secret',
        ], $site->settings['database']);
        $this->assertSame(['news', 'products'], $site->settings['enabled_modules']);
        $this->assertSame([
            ['name' => 'Blog', 'slug' => 'blog', 'type' => 'single'],
            ['name' => 'Events', 'slug' => 'events', 'type' => 'multi'],
        ], $site->settings['custom_modules']);
        $this->assertSame($domain, $site->settings['deployment']['production_domain']);
        $this->assertSame('https://' . $domain, $site->settings['deployment']['frontend_url']);
        $this->assertSame('https://' . $domain . '/cms', $site->settings['deployment']['admin_url']);
        $this->assertSame('https://github.com/example/test-site.git', $site->settings['deployment']['git_repository_url']);
        $this->assertSame('2026-06-22 10:00:00', $site->settings['deployment']['initialized_at']);
        $this->assertSame('2026-06-22 11:00:00', $site->settings['deployment']['domain_bound_at']);
        $this->assertSame('Custom site notes.', $site->settings['deployment']['notes']);
        $adminAccess = app(SiteDeploymentManager::class)->adminAccessForDisplay($site);
        $this->assertSame('https://' . $domain . '/cms', $adminAccess['url']);
        $this->assertSame('admin', $adminAccess['username']);
        $this->assertNotEmpty($adminAccess['password']);
        $this->assertDatabaseHas('site_domains', [
            'site_id' => $site->id,
            'domain' => $domain,
            'is_primary' => true,
            'force_https' => true,
        ]);
        $this->assertDatabaseHas('site_domains', [
            'site_id' => $site->id,
            'domain' => $slug . '.secondary.test',
            'is_primary' => false,
        ]);
        $this->assertDatabaseHas('languages', [
            'site_id' => $site->id,
            'slug' => 'tw',
            'locale' => 'zh-Hant-TW',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('content_types', [
            'site_id' => $site->id,
            'code' => 'news',
            'name' => '最新消息',
        ]);
        $this->assertDatabaseHas('taxonomies', [
            'site_id' => $site->id,
            'code' => 'newsCate',
            'name' => '最新消息分類',
        ]);
        $this->assertFileExists($setPath . DIRECTORY_SEPARATOR . 'newsSet.php');
        $this->assertFileExists($setPath . DIRECTORY_SEPARATOR . 'productSet.php');
        $this->assertFileExists($setPath . DIRECTORY_SEPARATOR . 'blogSet.php');
        $this->assertFileExists($setPath . DIRECTORY_SEPARATOR . 'eventsCateSet.php');
        $this->assertFileDoesNotExist($setPath . DIRECTORY_SEPARATOR . 'contactusSet.php');
        $this->assertArrayHasKey('blog', CmsSetLoader::allKnown('list'));
        $this->assertArrayHasKey('events', CmsSetLoader::allKnown('list'));
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => Site::query()->where('slug', 'main-site')->value('id'),
            'location' => 'backend',
            'module_key' => 'sites',
            'title' => '多站管理',
        ]);
        $this->assertDatabaseMissing('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'sites',
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'blog',
            'title' => 'Blog',
            'route_name' => 'admin.blog.index',
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'blog.categories',
            'title' => '分類',
            'route_name' => 'admin.taxonomies.index',
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'events.tags',
            'title' => '標籤',
            'route_name' => 'admin.taxonomies.index',
        ]);
    }

    public function test_creating_site_generates_frontend_project_with_site_env(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'frontend-project-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $templatePath = storage_path('framework/testing/next-template-' . $slug);
        $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path('framework/testing/' . $slug));
        config(['cms.platform.site_workspace_root' => storage_path('framework/testing')]);

        File::deleteDirectory($templatePath);
        File::deleteDirectory($targetPath);
        File::ensureDirectoryExists($templatePath . DIRECTORY_SEPARATOR . 'app');
        File::ensureDirectoryExists($templatePath . DIRECTORY_SEPARATOR . 'node_modules');
        File::ensureDirectoryExists($templatePath . DIRECTORY_SEPARATOR . '.next');
        File::put($templatePath . DIRECTORY_SEPARATOR . 'package.json', json_encode(['name' => 'template-next-platform'], JSON_PRETTY_PRINT));
        File::put($templatePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'page.js', 'export default function Page() { return null; }');
        File::put($templatePath . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'skip.txt', 'skip');
        File::put($templatePath . DIRECTORY_SEPARATOR . '.next' . DIRECTORY_SEPARATOR . 'skip.txt', 'skip');

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Frontend Project Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'api_access_token' => 'site-front-token',
                'frontend_template_path' => $templatePath,
                'enabled_modules' => ['news'],
            ]);

        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $env = File::get($targetPath . DIRECTORY_SEPARATOR . '.env.local');

        $this->assertDirectoryExists($targetPath);
        $this->assertFileExists($targetPath . DIRECTORY_SEPARATOR . 'package.json');
        $this->assertFileExists($targetPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'page.js');
        $this->assertFileDoesNotExist($targetPath . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'skip.txt');
        $this->assertFileDoesNotExist($targetPath . DIRECTORY_SEPARATOR . '.next' . DIRECTORY_SEPARATOR . 'skip.txt');
        $this->assertStringContainsString('API_BASE_URL=' . rtrim(config('app.url'), '/'), $env);
        $this->assertStringContainsString('API_SITE=' . $slug, $env);
        $this->assertStringContainsString('API_LANGUAGE=tw', $env);
        $this->assertStringContainsString('API_ACCESS_TOKEN=site-front-token', $env);
        $this->assertStringContainsString('NEXT_PUBLIC_SITE_URL=', $env);
        $this->assertSame($targetPath, $site->settings['repository_path']);
        $this->assertSame($targetPath . DIRECTORY_SEPARATOR . 'cms' . DIRECTORY_SEPARATOR . 'set', $site->settings['cms_set_path']);
        $this->assertSame($targetPath, $site->settings['deployment']['frontend_project_path']);
        $this->assertNotEmpty($site->settings['deployment']['frontend_generated_at']);
    }

    public function test_updating_site_only_syncs_frontend_env_file(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'frontend-env-sync-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $templatePath = storage_path('framework/testing/next-template-' . $slug);
        $targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path('framework/testing/' . $slug));
        config(['cms.platform.site_workspace_root' => storage_path('framework/testing')]);

        File::deleteDirectory($templatePath);
        File::deleteDirectory($targetPath);
        File::ensureDirectoryExists($templatePath . DIRECTORY_SEPARATOR . 'app');
        File::put($templatePath . DIRECTORY_SEPARATOR . 'package.json', json_encode(['name' => 'template-next-platform'], JSON_PRETTY_PRINT));
        File::put($templatePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'page.js', 'export default function Page() { return "template"; }');

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Frontend Env Sync Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'api_access_token' => 'old-front-token',
                'frontend_template_path' => $templatePath,
                'enabled_modules' => ['news'],
            ]);

        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $mainSite = Site::query()->where('slug', 'main-site')->firstOrFail();
        File::put($targetPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'page.js', 'custom frontend code');

        $this
            ->withSession(['admin_user_id' => $admin->id, 'current_site_id' => $mainSite->id])
            ->put(route('admin.sites.update', $site), [
                'tenant_id' => $tenant->id,
                'name' => 'Frontend Env Sync Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'api_access_token' => 'new-front-token',
                'frontend_template_path' => $templatePath,
                'repository_path' => $targetPath,
                'frontend_url' => 'https://frontend-sync.example.test',
                'enabled_modules' => ['news'],
            ])
            ->assertRedirect(route('admin.sites.edit', $site));

        $env = File::get($targetPath . DIRECTORY_SEPARATOR . '.env.local');
        $site->refresh();

        $this->assertSame('custom frontend code', File::get($targetPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'page.js'));
        $this->assertStringContainsString('API_SITE=' . $slug, $env);
        $this->assertStringContainsString('API_ACCESS_TOKEN=new-front-token', $env);
        $this->assertStringContainsString('NEXT_PUBLIC_SITE_URL=https://frontend-sync.example.test', $env);
        $this->assertSame(['https://frontend-sync.example.test'], $site->settings['api_allowed_origins']);
        $this->assertNotEmpty($site->settings['deployment']['frontend_env_synced_at']);
    }

    public function test_site_database_defaults_use_site_slug(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'db-name-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'DB Name Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'enabled_modules' => ['news'],
            ]);

        $site = Site::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame($slug, $site->settings['database']['connection']);
        $this->assertSame($slug, $site->settings['database']['database']);
    }

    public function test_site_with_managed_data_cannot_be_deleted(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Protected Site',
            'slug' => 'protected-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);
        $site->contentTypes()->create([
            'code' => 'news',
            'name' => 'News',
            'is_active' => true,
            'config' => [],
        ]);

        $response = $this
            ->withSession(['admin_user_id' => $admin->id])
            ->delete(route('admin.sites.destroy', $site));

        $response->assertSessionHasErrors('site');
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    public function test_admin_can_switch_current_site_from_header(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $baseSite = Site::query()->where('slug', 'main-site')->firstOrFail();
        $targetSite = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Target Switch Site',
            'slug' => 'target-switch-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $this
            ->withSession([
                'admin_user_id' => $admin->id,
                'current_site_id' => $baseSite->id,
            ])
            ->post(route('admin.sites.switch'), [
                'site_id' => $targetSite->id,
                'redirect_to' => route('admin.dashboard'),
            ])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('current_site_id', $targetSite->id);
    }

    public function test_site_switch_redirect_is_limited_to_admin_pages(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $targetSite = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Safe Redirect Site',
            'slug' => 'safe-redirect-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.switch'), [
                'site_id' => $targetSite->id,
                'redirect_to' => 'https://evil.example.test/admin',
            ])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_child_site_cannot_access_site_management(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $childSite = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Child Site',
            'slug' => 'child-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $this
            ->withSession([
                'admin_user_id' => $admin->id,
                'current_site_id' => $childSite->id,
            ])
            ->get(route('admin.sites.index'))
            ->assertForbidden();
    }

    public function test_site_specific_set_files_override_shared_set_files(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $setPath = storage_path('framework/testing/site-set-' . strtolower(substr(md5((string) microtime(true)), 0, 8)));

        if (!is_dir($setPath)) {
            mkdir($setPath, 0777, true);
        }

        file_put_contents($setPath . DIRECTORY_SEPARATOR . 'newsSet.php', <<<'PHP'
<?php

return [
    'module' => 'news',
    'moduleName' => '站台專屬消息',
    'model' => \App\Models\Content::class,
    'strategy' => 'content',
    'content_type' => 'news',
    'listPage' => [
        'columns' => [],
    ],
];
PHP);
        file_put_contents($setPath . DIRECTORY_SEPARATOR . 'keywordsInfoSet.php', <<<'PHP'
<?php

return [
    'moduleName' => '不應覆蓋的站台全站設定',
    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_title', 'label' => '網站名稱'],
            ],
        ],
    ],
];
PHP);

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Set Override Site',
            'slug' => 'set-override-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => ['cms_set_path' => $setPath],
        ]);

        $config = CmsSetLoader::get('news', 'list', $site);
        $sharedConfig = CmsSetLoader::get('keywordsInfo', 'info', $site);

        $this->assertSame('站台專屬消息', $config['moduleName'] ?? null);
        $this->assertNotSame('不應覆蓋的站台全站設定', $sharedConfig['moduleName'] ?? null);
    }

    public function test_site_update_does_not_write_set_files(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'no-file-update-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $setPath = storage_path('framework/testing/site-update-set-' . $slug);

        if (!is_dir($setPath)) {
            mkdir($setPath, 0777, true);
        }

        $existingFile = $setPath . DIRECTORY_SEPARATOR . 'productSet.php';
        file_put_contents($existingFile, "<?php\n\nreturn ['moduleName' => 'Do Not Overwrite'];\n");

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'No File Update Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [
                'cms_set_path' => $setPath,
                'enabled_modules' => ['news'],
            ],
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->put(route('admin.sites.update', $site), [
                'tenant_id' => $tenant->id,
                'name' => 'No File Update Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'cms_set_path' => $setPath,
                'enabled_modules' => ['news', 'products'],
            ])
            ->assertRedirect(route('admin.sites.edit', $site));

        $this->assertSame("<?php\n\nreturn ['moduleName' => 'Do Not Overwrite'];\n", file_get_contents($existingFile));
        $this->assertFileDoesNotExist($setPath . DIRECTORY_SEPARATOR . 'productCateSet.php');
        $this->assertSame(['news', 'products'], $site->refresh()->settings['enabled_modules']);
    }

    public function test_custom_module_slug_cannot_conflict_with_reserved_modules(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->from(route('admin.sites.create'))
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Reserved Module Site',
                'slug' => 'reserved-module-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'enabled_modules' => ['news'],
                'custom_modules' => [
                    ['name' => 'Bad News', 'slug' => 'news', 'type' => 'single'],
                ],
            ])
            ->assertRedirect(route('admin.sites.create'))
            ->assertSessionHasErrors('custom_modules.0.slug');
    }

    public function test_legacy_standard_module_keys_are_normalized(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'legacy-module-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.store'), [
                'tenant_id' => $tenant->id,
                'name' => 'Legacy Module Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'enabled_modules' => ['news', 'product', 'contactus'],
            ]);

        $site = Site::query()->where('slug', $slug)->firstOrFail();

        $this->assertSame(['news', 'products', 'contact'], $site->settings['enabled_modules']);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'products',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'contact',
            'is_active' => true,
        ]);
    }

    public function test_removed_custom_module_backend_menu_is_disabled(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'disable-custom-module-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $setPath = storage_path('framework/testing/site-disable-custom-set-' . $slug);

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Disable Custom Module Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [
                'cms_set_path' => $setPath,
                'enabled_modules' => ['news'],
                'custom_modules' => [
                    ['name' => 'Blog', 'slug' => 'blog', 'type' => 'single'],
                ],
            ],
        ]);

        app(\App\Support\SiteModuleManager::class)->syncDatabase($site);

        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'blog',
            'is_active' => true,
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->put(route('admin.sites.update', $site), [
                'tenant_id' => $tenant->id,
                'name' => 'Disable Custom Module Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'cms_set_path' => $setPath,
                'enabled_modules' => ['news'],
                'custom_modules' => [],
            ])
            ->assertRedirect(route('admin.sites.edit', $site));

        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'blog',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'blog.categories',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_git_push_site_repository(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'git-push-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $repoPath = storage_path('framework/testing/git-site-' . $slug);
        $remotePath = storage_path('framework/testing/git-remote-' . $slug . '.git');

        if (!is_dir($repoPath)) {
            mkdir($repoPath, 0777, true);
        }

        file_put_contents($repoPath . DIRECTORY_SEPARATOR . 'README.md', '# ' . $slug . PHP_EOL);
        (new Process(['git', 'init', '--bare', $remotePath]))->mustRun();

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Git Push Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [
                'repository_path' => $repoPath,
                'deployment' => [
                    'git_repository_url' => $remotePath,
                ],
            ],
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.git-push', $site))
            ->assertRedirect();

        $site->refresh();
        $this->assertSame('success', $site->settings['deployment']['git_push_status'] ?? null);
        $this->assertNotEmpty($site->settings['deployment']['git_pushed_at'] ?? null);
        $remoteList = trim((new Process(['git', 'remote'], $repoPath))->mustRun()->getOutput());
        $this->assertSame('origin', $remoteList);

        $branch = trim((new Process(['git', 'branch', '--show-current'], $repoPath))->mustRun()->getOutput());
        $this->assertSame('main', $branch);
    }

    public function test_git_push_creates_gitlab_project_when_repository_url_is_empty(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'auto-gitlab-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $repoPath = storage_path('framework/testing/git-site-' . $slug);
        $remotePath = storage_path('framework/testing/git-remote-' . $slug . '.git');

        if (!is_dir($repoPath)) {
            mkdir($repoPath, 0777, true);
        }

        file_put_contents($repoPath . DIRECTORY_SEPARATOR . 'README.md', '# ' . $slug . PHP_EOL);
        (new Process(['git', 'init', '--bare', $remotePath]))->mustRun();

        config([
            'cms.platform.gitlab.url' => 'https://gitlab.example.test',
            'cms.platform.gitlab.token' => 'secret-token',
            'cms.platform.gitlab.namespace_id' => '100',
            'cms.platform.gitlab.namespace_path' => 'goods-design',
        ]);

        Http::fake([
            'gitlab.example.test/api/v4/projects' => Http::response([
                'id' => 9001,
                'path' => $slug,
                'path_with_namespace' => 'goods-design/' . $slug,
                'http_url_to_repo' => $remotePath,
            ], 201),
        ]);

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Auto GitLab Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [
                'repository_path' => $repoPath,
                'deployment' => [],
            ],
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->post(route('admin.sites.git-push', $site))
            ->assertRedirect();

        $site->refresh();
        $this->assertSame($remotePath, $site->settings['deployment']['git_repository_url'] ?? null);
        $this->assertSame(9001, $site->settings['deployment']['gitlab_project_id'] ?? null);
        $this->assertSame('goods-design/' . $slug, $site->settings['deployment']['gitlab_project_path'] ?? null);
        $this->assertSame('success', $site->settings['deployment']['git_push_status'] ?? null);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://gitlab.example.test/api/v4/projects'
            && $request['path'] === $slug
            && $request['namespace_id'] === '100');
    }

    public function test_site_edit_page_shows_admin_access_and_git_push_button(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Access Info Site',
            'slug' => 'access-info-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8)),
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [
                'deployment' => [
                    'admin_url' => 'https://access.example.test/cms',
                    'git_repository_url' => 'https://gitlab.com/example/access-info-site.git',
                ],
            ],
        ]);

        app(SiteDeploymentManager::class)->ensureAdminAccess($site);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->get(route('admin.sites.edit', $site))
            ->assertOk()
            ->assertSee('Git Push')
            ->assertSee('後台登入資訊')
            ->assertSee('上線資訊')
            ->assertDontSee('API 設定')
            ->assertSee('https://access.example.test/cms')
            ->assertSee('admin');
    }

    public function test_admin_access_can_be_edited_after_generation(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'editable-access-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Editable Access Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $site = app(SiteDeploymentManager::class)->ensureAdminAccess($site);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->put(route('admin.sites.update', $site), [
                'tenant_id' => $tenant->id,
                'name' => 'Editable Access Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'enabled_modules' => ['news'],
                'admin_access_url' => 'https://editable.example.test/admin',
                'admin_access_username' => 'manager',
                'admin_access_password' => 'changed-secret',
            ])
            ->assertRedirect(route('admin.sites.edit', $site));

        $access = app(SiteDeploymentManager::class)->adminAccessForDisplay($site->refresh());
        $this->assertSame('https://editable.example.test/admin', $access['url']);
        $this->assertSame('manager', $access['username']);
        $this->assertSame('changed-secret', $access['password']);
    }

    public function test_empty_admin_access_password_keeps_existing_password(): void
    {
        [$admin, $tenant] = $this->adminAndTenant();
        $slug = 'keep-access-password-site-' . strtolower(substr(md5((string) microtime(true)), 0, 8));
        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Keep Access Password Site',
            'slug' => $slug,
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $site = app(SiteDeploymentManager::class)->syncAdminAccess($site, [
            'admin_access_url' => 'https://keep.example.test/admin',
            'admin_access_username' => 'admin',
            'admin_access_password' => 'original-secret',
        ]);

        $this
            ->withSession(['admin_user_id' => $admin->id])
            ->put(route('admin.sites.update', $site), [
                'tenant_id' => $tenant->id,
                'name' => 'Keep Access Password Site',
                'slug' => $slug,
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
                'enabled_modules' => ['news'],
                'admin_access_url' => 'https://keep.example.test/cms',
                'admin_access_username' => 'editor',
                'admin_access_password' => '',
            ])
            ->assertRedirect(route('admin.sites.edit', $site));

        $access = app(SiteDeploymentManager::class)->adminAccessForDisplay($site->refresh());
        $this->assertSame('https://keep.example.test/cms', $access['url']);
        $this->assertSame('editor', $access['username']);
        $this->assertSame('original-secret', $access['password']);
    }

    private function adminAndTenant(): array
    {
        $this->ensureSchema();

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'test-tenant'],
            ['name' => 'Test Tenant', 'status' => 'active', 'plan_code' => 'test']
        );

        $admin = AdminUser::query()->firstOrCreate(
            ['email' => 'site-test-admin@example.test'],
            [
                'name' => 'Site Test Admin',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $mainSite = Site::query()->firstOrCreate(
            ['slug' => 'main-site'],
            [
                'tenant_id' => $tenant->id,
                'name' => '主網站',
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
            ]
        );

        if (Schema::hasTable('cms_menus')) {
            \Illuminate\Support\Facades\DB::table('cms_menus')->updateOrInsert(
                [
                    'site_id' => $mainSite->id,
                    'location' => 'backend',
                    'parent_id' => null,
                    'module_key' => 'sites',
                ],
                [
                    'locale' => 'zh-Hant-TW',
                    'title' => '多站管理',
                    'type' => 'route',
                    'url' => null,
                    'route_name' => 'admin.sites.index',
                    'icon' => 'bx bx-buildings',
                    'target' => '_self',
                    'is_active' => true,
                    'sort_order' => 99,
                    'settings' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }

        return [$admin, $tenant];
    }

    private function ensureSchema(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->string('plan_code')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->string('default_locale')->default('zh-Hant-TW');
                $table->string('timezone')->default('Asia/Taipei');
                $table->string('currency_code', 10)->default('TWD');
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('site_domains')) {
            Schema::create('site_domains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id');
                $table->string('domain')->unique();
                $table->boolean('is_primary')->default(false);
                $table->boolean('force_https')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('status')->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('content_types')) {
            Schema::create('content_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id');
                $table->string('code');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id');
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->string('slug', 40);
                $table->string('locale', 40);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taxonomies')) {
            Schema::create('taxonomies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id');
                $table->string('code');
                $table->string('name');
                $table->boolean('is_hierarchical')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cms_menus')) {
            Schema::create('cms_menus', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id');
                $table->foreignId('parent_id')->nullable();
                $table->string('location')->default('backend');
                $table->string('locale', 40)->default('zh-Hant-TW');
                $table->string('title');
                $table->string('type')->default('custom');
                $table->string('url')->nullable();
                $table->string('route_name')->nullable();
                $table->string('module_key')->nullable();
                $table->string('icon')->nullable();
                $table->string('target')->default('_self');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->json('settings')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }
}
