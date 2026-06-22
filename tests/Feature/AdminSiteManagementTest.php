<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Site;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
        $this->assertDatabaseHas('cms_menus', [
            'site_id' => $site->id,
            'location' => 'backend',
            'module_key' => 'sites',
            'title' => '多站管理',
        ]);
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
        $baseSite = Site::query()->where('slug', 'base-test-site')->firstOrFail();
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

        Site::query()->firstOrCreate(
            ['slug' => 'base-test-site'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Base Test Site',
                'status' => 'active',
                'default_locale' => 'zh-Hant-TW',
                'timezone' => 'Asia/Taipei',
                'currency_code' => 'TWD',
            ]
        );

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
