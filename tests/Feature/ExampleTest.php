<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Site;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A basic test example.
     */
    public function test_root_redirects_to_admin_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_guest_admin_is_redirected_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_admin_pages_render_legacy_cms_shell(): void
    {
        if (!Schema::hasTable('admin_users')) {
            $this->markTestSkipped('Admin database schema is not available in the test connection.');
        }

        $admin = AdminUser::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/contents')
            ->assertOk()
            ->assertSee('sidebar-left', false)
            ->assertSee('card-modern', false)
            ->assertSee('table-ecommerce-simple', false);

        $this->withSession(['admin_user_id' => $admin->id])
            ->get('/admin/contents/create')
            ->assertOk()
            ->assertSee('ecommerce-form', false)
            ->assertSee('card-big-info', false);
    }

    public function test_taxonomy_delete_requires_confirmation_when_related_data_exists(): void
    {
        $this->ensureMinimalCmsSchemaForTaxonomyDeleteGuard();

        $admin = AdminUser::query()->create([
            'name' => 'Test Admin',
            'email' => 'taxonomy-delete-guard@example.test',
            'password' => 'testing',
            'status' => 'active',
        ]);
        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => '測試網站',
            'slug' => 'taxonomy-delete-guard',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);
        $contentType = ContentType::query()->create([
            'site_id' => $site->id,
            'code' => 'test-content',
            'name' => '測試內容',
            'config' => [],
            'is_active' => true,
        ]);
        $taxonomy = Taxonomy::query()->create([
            'site_id' => $site->id,
            'code' => 'testDeleteGuard',
            'name' => '刪除防呆測試',
            'is_hierarchical' => true,
        ]);
        $term = TaxonomyTerm::query()->create([
            'taxonomy_id' => $taxonomy->id,
            'locale' => $site->default_locale,
            'name' => '測試分類',
            'slug' => 'test-delete-guard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $content = Content::query()->create([
            'site_id' => $site->id,
            'content_type_id' => $contentType->id,
            'status' => 'published',
            'is_pinned' => false,
            'sort_order' => 1,
            'view_count' => 0,
            'published_at' => now(),
        ]);

        DB::table('content_term')->insert([
            'content_id' => $content->id,
            'taxonomy_term_id' => $term->id,
            'sort_order' => 1,
        ]);

        $session = ['admin_user_id' => $admin->id, 'current_site_id' => $site->id];

        $this->withSession($session)
            ->deleteJson(route('admin.taxonomies.destroy', [$taxonomy->code, $term]))
            ->assertStatus(409)
            ->assertJsonPath('needs_confirm', true)
            ->assertJsonPath('title', '分類內還有資料');

        $this->assertDatabaseHas('taxonomy_terms', [
            'id' => $term->id,
            'deleted_at' => null,
        ]);

        $this->withSession($session)
            ->deleteJson(route('admin.taxonomies.destroy', [$taxonomy->code, $term]), ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull(TaxonomyTerm::withTrashed()->find($term->id)?->deleted_at);
        $this->assertDatabaseHas('content_term', [
            'content_id' => $content->id,
            'taxonomy_term_id' => $term->id,
        ]);
    }

    private function ensureMinimalCmsSchemaForTaxonomyDeleteGuard(): void
    {
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
                $table->string('locale')->default('zh-Hant-TW');
                $table->string('name');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->softDeletes();
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

        if (!Schema::hasTable('content_term')) {
            Schema::create('content_term', function (Blueprint $table): void {
                $table->unsignedBigInteger('content_id');
                $table->unsignedBigInteger('taxonomy_term_id');
                $table->unsignedInteger('sort_order')->default(1);
            });
        }

        if (!Schema::hasTable('product_category_product')) {
            Schema::create('product_category_product', function (Blueprint $table): void {
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('taxonomy_term_id');
                $table->unsignedInteger('sort_order')->default(1);
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('status')->default('draft');
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }
}
