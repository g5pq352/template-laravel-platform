<?php

namespace Tests\Feature;

use App\Models\CmsMenu;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\ContentType;
use App\Models\HomeDisplay;
use App\Models\Language;
use App\Models\Site;
use App\Models\SiteDomain;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicSiteApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->ensureMinimalApiSchema();
    }

    public function test_public_api_resolves_site_and_returns_frontend_data(): void
    {
        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'API Test Site',
            'slug' => 'api-test-site',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => ['theme' => 'default'],
        ]);

        Language::query()->create([
            'site_id' => $site->id,
            'name' => '繁體中文',
            'name_en' => 'Traditional Chinese',
            'slug' => 'tw',
            'locale' => 'zh-Hant-TW',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CmsMenu::query()->create([
            'site_id' => $site->id,
            'location' => 'main',
            'title' => '首頁',
            'type' => 'link',
            'url' => '/',
            'target' => '_self',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/site?site=api-test-site&language=tw')
            ->assertOk()
            ->assertJsonPath('data.slug', 'api-test-site')
            ->assertJsonPath('data.locale', 'zh-Hant-TW')
            ->assertJsonPath('data.settings.theme', 'default');

        $this->getJson('/api/languages?site=api-test-site')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'tw')
            ->assertJsonPath('data.0.locale', 'zh-Hant-TW');

        $this->getJson('/api/menus?site=api-test-site&location=main')
            ->assertOk()
            ->assertJsonPath('data.0.title', '首頁')
            ->assertJsonPath('data.0.url', '/');
    }

    public function test_api_origin_whitelist_blocks_direct_requests_and_allows_frontend_origin(): void
    {
        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'Origin Locked Site',
            'slug' => 'origin-locked-site',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => ['api_allowed_origins' => ['https://www.example.test']],
        ]);

        Language::query()->create([
            'site_id' => $site->id,
            'name' => '繁體中文',
            'name_en' => 'Traditional Chinese',
            'slug' => 'tw',
            'locale' => 'zh-Hant-TW',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/site?site=origin-locked-site&language=tw')
            ->assertForbidden()
            ->assertJsonPath('message', 'API origin not allowed.');

        $this
            ->withHeader('Origin', 'https://www.example.test')
            ->getJson('/api/site?site=origin-locked-site&language=tw')
            ->assertOk()
            ->assertJsonPath('data.slug', 'origin-locked-site');

        $this
            ->withHeader('Origin', 'https://evil.example.test')
            ->getJson('/api/site?site=origin-locked-site&language=tw')
            ->assertForbidden();
    }

    public function test_public_api_returns_active_sites_for_current_tenant(): void
    {
        $site = Site::query()->create([
            'tenant_id' => 99,
            'name' => 'Main Tenant Site',
            'slug' => 'main-tenant-site',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [],
        ]);

        $secondSite = Site::query()->create([
            'tenant_id' => 99,
            'name' => 'Second Tenant Site',
            'slug' => 'second-tenant-site',
            'status' => 'active',
            'default_locale' => 'en',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [],
        ]);

        Site::query()->create([
            'tenant_id' => 99,
            'name' => 'Inactive Tenant Site',
            'slug' => 'inactive-tenant-site',
            'status' => 'inactive',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [],
        ]);

        Site::query()->create([
            'tenant_id' => 100,
            'name' => 'Other Tenant Site',
            'slug' => 'other-tenant-site',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [],
        ]);

        SiteDomain::query()->create([
            'site_id' => $secondSite->id,
            'domain' => 'second.example.test',
            'is_primary' => true,
            'force_https' => true,
        ]);

        $this->getJson('/api/sites?site=main-tenant-site')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'main-tenant-site')
            ->assertJsonPath('data.0.is_current', true)
            ->assertJsonPath('data.1.slug', 'second-tenant-site')
            ->assertJsonPath('data.1.primary_domain', 'second.example.test')
            ->assertJsonPath('data.1.domains.0.force_https', true);
    }

    public function test_public_api_returns_home_display_items_from_configured_module(): void
    {
        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'Home API Site',
            'slug' => 'home-api-site',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
            'settings' => [],
        ]);

        Language::query()->create([
            'site_id' => $site->id,
            'name' => 'Traditional Chinese',
            'name_en' => 'Traditional Chinese',
            'slug' => 'tw',
            'locale' => 'zh-Hant-TW',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $type = ContentType::query()->create([
            'site_id' => $site->id,
            'code' => 'news',
            'name' => 'News',
            'config' => [],
            'is_active' => true,
        ]);

        $content = Content::query()->create([
            'site_id' => $site->id,
            'content_type_id' => $type->id,
            'status' => 'published',
            'is_pinned' => false,
            'sort_order' => 1,
            'view_count' => 0,
            'published_at' => now(),
        ]);

        ContentTranslation::query()->create([
            'content_id' => $content->id,
            'locale' => 'zh-Hant-TW',
            'title' => 'Home News',
            'slug' => 'home-news',
            'summary' => 'Summary',
            'body' => 'Body',
            'custom_fields' => [
                'cover' => 'cover.jpg',
                'rooms' => [
                    [
                        'name' => 'Room A',
                        'image' => [
                            'disk' => 'public',
                            'path' => 'sites/1/content/dynamic/room/room-a.jpg',
                            'title' => 'Room image',
                        ],
                    ],
                ],
            ],
        ]);

        $mediaId = $this->insertMediaFile($site->id, [
            'path' => 'sites/1/content/newsCover/cover.jpg',
            'original_name' => 'cover.jpg',
            'alt_text' => 'Cover alt',
            'width' => 1200,
            'height' => 630,
        ]);

        \Illuminate\Support\Facades\DB::table('content_media')->insert([
            'content_id' => $content->id,
            'media_file_id' => $mediaId,
            'role' => 'newsCover',
            'sort_order' => 1,
            'metadata' => null,
        ]);

        HomeDisplay::query()->create([
            'site_id' => $site->id,
            'content_id' => $content->id,
            'module' => 'news',
            'locale' => 'zh-Hant-TW',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->getJson('/api/home-display?site=home-api-site&language=tw')
            ->assertOk()
            ->assertJsonPath('meta.module', 'news')
            ->assertJsonPath('meta.target_type', 'news')
            ->assertJsonPath('data.0.title', 'Home News')
            ->assertJsonPath('data.0.slug', 'home-news')
            ->assertJsonPath('data.0.home_sort_order', 1)
            ->assertJsonPath('data.0.custom_fields.cover', 'cover.jpg')
            ->assertJsonPath('data.0.custom_fields.rooms.0.image.url', url('/storage/sites/1/content/dynamic/room/room-a.jpg'))
            ->assertJsonPath('data.0.media_by_role.newsCover.0.url', url('/storage/sites/1/content/newsCover/cover.jpg'))
            ->assertJsonPath('data.0.media_by_role.newsCover.0.alt', 'Cover alt');
    }

    private function insertMediaFile(int $siteId, array $overrides = []): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('media_files')->insertGetId(array_merge([
            'site_id' => $siteId,
            'disk' => 'public',
            'path' => 'sites/1/content/default.jpg',
            'original_name' => 'default.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 12345,
            'width' => 800,
            'height' => 600,
            'alt_text' => '',
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function ensureMinimalApiSchema(): void
    {
        if (!Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('status')->default('active');
                $table->string('default_locale')->default('zh-Hant-TW');
                $table->string('timezone')->default('Asia/Taipei');
                $table->string('currency_code')->default('TWD');
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('name');
                $table->string('name_en')->nullable();
                $table->string('slug', 40);
                $table->string('locale', 40);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('site_domains')) {
            Schema::create('site_domains', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('domain')->unique();
                $table->boolean('is_primary')->default(false);
                $table->boolean('force_https')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cms_menus')) {
            Schema::create('cms_menus', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('location')->default('main');
                $table->string('title');
                $table->string('type')->default('link');
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

        if (!Schema::hasTable('content_types')) {
            Schema::create('content_types', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('code');
                $table->string('name');
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contents')) {
            Schema::create('contents', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('content_type_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('status')->default('draft');
                $table->boolean('is_pinned')->default(false);
                $table->unsignedInteger('sort_order')->default(1);
                $table->unsignedInteger('view_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('content_translations')) {
            Schema::create('content_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('content_id');
                $table->string('locale', 40);
                $table->string('title')->nullable();
                $table->string('slug')->nullable();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->json('custom_fields')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taxonomies')) {
            Schema::create('taxonomies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('code');
                $table->string('name');
                $table->boolean('is_hierarchical')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('taxonomy_terms')) {
            Schema::create('taxonomy_terms', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('taxonomy_id');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->string('locale', 40)->default('zh-Hant-TW');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('content_term')) {
            Schema::create('content_term', function (Blueprint $table): void {
                $table->unsignedBigInteger('content_id');
                $table->unsignedBigInteger('taxonomy_term_id');
                $table->unsignedInteger('sort_order')->default(1);
            });
        }

        if (!Schema::hasTable('home_displays')) {
            Schema::create('home_displays', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->unsignedBigInteger('content_id');
                $table->string('module', 80)->default('news');
                $table->string('locale', 40)->default('zh-Hant-TW');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('media_files')) {
            Schema::create('media_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('alt_text')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('content_media')) {
            Schema::create('content_media', function (Blueprint $table): void {
                $table->unsignedBigInteger('content_id');
                $table->unsignedBigInteger('media_file_id');
                $table->string('role')->default('default');
                $table->unsignedInteger('sort_order')->default(1);
                $table->json('metadata')->nullable();
            });
        }

        if (!Schema::hasTable('product_media')) {
            Schema::create('product_media', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('media_file_id');
                $table->string('role')->default('default');
                $table->unsignedInteger('sort_order')->default(1);
            });
        }
    }
}
