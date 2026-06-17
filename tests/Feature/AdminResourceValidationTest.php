<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\AdminUser;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Site;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class AdminResourceValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_required_image_upload_is_blocked_before_store_when_missing(): void
    {
        $request = Request::create('/admin/news', 'POST', [
            'title' => 'Draft title',
        ]);

        $config = [
            'strategy' => 'content',
            'form_sections' => [
                'main' => [
                    'fields' => [
                        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true],
                        ['name' => 'cover', 'label' => 'Cover Image', 'type' => 'image_upload', 'required' => true, 'file_type' => 'cover'],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeControllerMethod('validatedData', [$request, $config, 'zh-Hant-TW', null]);
            $this->fail('Missing required image upload was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cover', $exception->errors());
        }
    }

    public function test_required_dynamic_upload_is_blocked_when_row_has_values_but_no_file(): void
    {
        $request = Request::create('/admin/news', 'POST', [
            'rooms' => [
                0 => [
                    '_uid' => 'row-1',
                    'name' => 'Room A',
                ],
            ],
        ]);

        $config = [
            'strategy' => 'content',
            'form_sections' => [
                'rooms' => [
                    'fields' => [
                        [
                            'name' => 'rooms',
                            'label' => 'Rooms',
                            'type' => 'dynamic_fields',
                            'fields' => [
                                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                                ['name' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => true],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->invokeControllerMethod('validatedData', [$request, $config, 'zh-Hant-TW', null]);
            $this->fail('Missing required dynamic upload was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rooms.0.photo', $exception->errors());
        }
    }

    public function test_dynamic_rows_are_normalized_by_numeric_order_after_add_and_sort(): void
    {
        $request = Request::create('/admin/news', 'POST', [
            'rooms' => [
                0 => ['name' => 'Room 1'],
                10 => ['name' => 'Room 11'],
                2 => ['name' => 'Room 3'],
            ],
        ]);

        $field = [
            'name' => 'rooms',
            'fields' => [
                ['name' => 'name', 'type' => 'text'],
            ],
        ];

        $rows = $this->invokeControllerMethod('normalizeDynamicRows', [$request, $field, 1, 'content', []]);

        $this->assertSame(['Room 1', 'Room 3', 'Room 11'], array_column($rows, 'name'));
    }

    public function test_dropzone_upload_stores_media_for_configured_image_field(): void
    {
        $this->ensureMinimalDropzoneSchema();

        Storage::fake('public');

        $admin = AdminUser::query()->create([
            'name' => 'Dropzone Test Admin',
            'email' => 'dropzone-test-' . uniqid() . '@example.test',
            'password' => 'testing',
            'status' => 'active',
        ]);

        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'Dropzone Test Site',
            'slug' => 'dropzone-test-' . uniqid(),
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
            'status' => 'draft',
            'is_pinned' => false,
            'sort_order' => 1,
            'view_count' => 0,
            'published_at' => now(),
        ]);

        $content->translations()->create([
            'locale' => 'zh-Hant-TW',
            'title' => 'Dropzone Test News',
            'slug' => 'dropzone-test-news',
        ]);

        $file = UploadedFile::fake()->image('dropzone.jpg', 1030, 570)->size(512);

        $response = $this->withSession([
            'admin_user_id' => $admin->id,
            'current_site_id' => $site->id,
        ])->postJson(route('admin.news.dropzone', $content->id), [
            'field' => 'image',
            'file_type' => 'image',
            'file' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('files.0.file_type', 'image');

        $this->assertDatabaseHas('media_files', [
            'site_id' => $site->id,
            'original_name' => 'dropzone.jpg',
        ]);

        $mediaId = (int) $response->json('files.0.id');
        $this->assertDatabaseHas('content_media', [
            'content_id' => $content->id,
            'media_file_id' => $mediaId,
            'role' => 'image',
        ]);
    }

    public function test_clone_item_deep_copies_media_and_dynamic_files(): void
    {
        $this->ensureMinimalDropzoneSchema();

        Storage::fake('public');
        Storage::disk('public')->put('sites/1/content/newsCover/source-cover.jpg', 'cover-content');
        Storage::disk('public')->put('sites/1/content/dynamic/room/source-room.jpg', 'room-content');

        $site = Site::query()->create([
            'tenant_id' => 1,
            'name' => 'Clone Test Site',
            'slug' => 'clone-test-' . uniqid(),
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
            'is_pinned' => true,
            'sort_order' => 1,
            'view_count' => 0,
            'published_at' => now(),
        ]);

        $content->translations()->create([
            'locale' => 'zh-Hant-TW',
            'title' => 'Original News',
            'slug' => 'original-news',
            'custom_fields' => [
                'rooms' => [
                    [
                        'name' => 'Room A',
                        'photo' => [
                            'disk' => 'public',
                            'path' => 'sites/1/content/dynamic/room/source-room.jpg',
                            'url' => '/storage/sites/1/content/dynamic/room/source-room.jpg',
                        ],
                    ],
                ],
            ],
        ]);

        $mediaId = (int) \Illuminate\Support\Facades\DB::table('media_files')->insertGetId([
            'site_id' => $site->id,
            'disk' => 'public',
            'path' => 'sites/1/content/newsCover/source-cover.jpg',
            'original_name' => 'source-cover.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 13,
            'width' => 1030,
            'height' => 570,
            'alt_text' => 'Cover',
            'metadata' => json_encode(['role' => 'newsCover']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('content_media')->insert([
            'content_id' => $content->id,
            'media_file_id' => $mediaId,
            'role' => 'newsCover',
            'sort_order' => 1,
            'metadata' => null,
        ]);

        $this->invokeControllerMethod('cloneItem', [$content, ['strategy' => 'content'], 'zh-Hant-TW']);

        $clone = Content::query()
            ->where('site_id', $site->id)
            ->whereKeyNot($content->id)
            ->firstOrFail();

        $cloneTranslation = $clone->translations()->where('locale', 'zh-Hant-TW')->firstOrFail();
        $cloneDynamicPath = $cloneTranslation->custom_fields['rooms'][0]['photo']['path'] ?? null;

        $this->assertNotSame($content->id, $clone->id);
        $this->assertSame('draft', $clone->status);
        $this->assertFalse((bool) $clone->is_pinned);
        $this->assertNotSame('sites/1/content/dynamic/room/source-room.jpg', $cloneDynamicPath);
        $this->assertTrue(Storage::disk('public')->exists($cloneDynamicPath));

        $clonePivot = \Illuminate\Support\Facades\DB::table('content_media')
            ->where('content_id', $clone->id)
            ->first();

        $this->assertNotNull($clonePivot);
        $this->assertNotSame($mediaId, (int) $clonePivot->media_file_id);

        $cloneMedia = \Illuminate\Support\Facades\DB::table('media_files')->where('id', $clonePivot->media_file_id)->first();
        $this->assertNotNull($cloneMedia);
        $this->assertNotSame('sites/1/content/newsCover/source-cover.jpg', $cloneMedia->path);
        $this->assertTrue(Storage::disk('public')->exists($cloneMedia->path));
    }

    private function ensureMinimalDropzoneSchema(): void
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

        if (!Schema::hasTable('media_files')) {
            Schema::create('media_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('site_id');
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('alt_text')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('content_media')) {
            Schema::create('content_media', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('content_id');
                $table->unsignedBigInteger('media_file_id');
                $table->string('role')->default('image');
                $table->unsignedInteger('sort_order')->default(1);
                $table->json('metadata')->nullable();
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
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function invokeControllerMethod(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(app(ResourceController::class), $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(app(ResourceController::class), $arguments);
    }
}
