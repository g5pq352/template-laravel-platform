<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Site;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AdminResourceLocaleDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_localized_resource_delete_only_soft_deletes_current_locale_translation(): void
    {
        $this->ensureMinimalContentSchema();

        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'Locale Delete Test',
            'slug' => 'locale-delete-test',
            'status' => 'active',
            'default_locale' => 'zh-Hant-TW',
            'timezone' => 'Asia/Taipei',
            'currency_code' => 'TWD',
        ]);

        $contentType = ContentType::query()->create([
            'site_id' => $site->id,
            'code' => 'news',
            'name' => 'News',
            'config' => [],
            'is_active' => true,
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

        $content->translations()->create([
            'locale' => 'zh-Hant-TW',
            'title' => '繁中標題',
            'slug' => 'tw-title',
        ]);

        $content->translations()->create([
            'locale' => 'en',
            'title' => 'English title',
            'slug' => 'en-title',
        ]);

        $controller = app(ResourceController::class);
        $method = new ReflectionMethod($controller, 'deleteResourceItem');
        $method->setAccessible(true);
        $method->invoke($controller, $content, ['strategy' => 'content'], 'zh-Hant-TW');

        $content->refresh();

        $this->assertFalse($content->trashed());
        $this->assertSame(0, $content->translations()->where('locale', 'zh-Hant-TW')->count());
        $this->assertSame(1, $content->translations()->withTrashed()->where('locale', 'zh-Hant-TW')->whereNotNull('deleted_at')->count());
        $this->assertSame(1, $content->translations()->where('locale', 'en')->count());
    }

    private function ensureMinimalContentSchema(): void
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
                $table->string('locale', 20);
                $table->string('title');
                $table->string('slug');
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->json('custom_fields')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }
    }
}
