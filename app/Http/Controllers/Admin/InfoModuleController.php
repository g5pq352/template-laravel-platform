<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentType;
use App\Support\AdminContext;
use App\Support\AdminLanguage;
use App\Support\CmsSetLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InfoModuleController extends Controller
{
    public function edit(AdminContext $context, string $module): View
    {
        $config = $this->moduleConfig($module);
        $site = $context->site();
        abort_unless($site, 404);
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $site->default_locale;

        [$content, $translation] = $this->ensureInfoContent($site->id, $locale, $module, $config);

        return view('admin.info.form', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'module' => $module,
            'moduleConfig' => $config,
            'content' => $content,
            'item' => $content,
            'translation' => $translation,
            'values' => $this->valuesFor($content, $translation, $config),
            'mediaByRole' => $this->mediaByRoleForItem($content),
            'statusOptions' => $config['status_options'] ?? [],
            'taxonomyOptions' => [],
            'selectedTermIds' => [],
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
            'languageParams' => $languageEnabled ? ['language' => $languageContext['slug']] : [],
        ]);
    }

    public function update(AdminContext $context, Request $request, string $module): RedirectResponse
    {
        $config = $this->moduleConfig($module);
        $site = $context->site();
        abort_unless($site, 404);
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $site->default_locale;

        [$content, $translation] = $this->ensureInfoContent($site->id, $locale, $module, $config);
        $data = $this->validatedData($request, $config);

        DB::transaction(function () use ($request, $site, $content, $translation, $config, $data): void {
            $customFields = $translation->custom_fields ?? [];

            foreach ($this->fields($config) as $field) {
                $name = $field['name'];

                if (($field['type'] ?? null) === 'image_upload') {
                    continue;
                }

                $customFields[$name] = $data[$name] ?? null;
            }

            $content->update([
                'status' => $data['status'] ?? $content->status,
                'published_at' => $data['published_at'] ?? $content->published_at,
            ]);

            $translation->update([
                'title' => $data['title'] ?? $translation->title,
                'summary' => $data['summary'] ?? $translation->summary,
                'body' => $data['body'] ?? $translation->body,
                'seo_title' => $data['seo_title'] ?? $translation->seo_title,
                'seo_description' => $data['seo_description'] ?? $translation->seo_description,
                'custom_fields' => $customFields,
            ]);

            $this->syncMediaUploads($request, $content, $config, $site->id);
        });

        return redirect()->route('admin.info.edit', [$module, ...($languageEnabled ? ['language' => $languageContext['slug']] : [])])->with('status', "{$config['label']}已更新。");
    }

    public function copyLanguage(AdminContext $context, Request $request, string $module): JsonResponse
    {
        $config = $this->moduleConfig($module);
        $site = $context->site();
        abort_unless($site, 404);

        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        abort_unless($languageEnabled, 422, '此模組不支援語系複製');

        $data = $request->validate([
            'target_language' => ['required', 'string', 'max:40'],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        $targetLanguage = AdminLanguage::activeLanguages($site)
            ->first(fn ($language) => in_array($data['target_language'], [$language->slug, $language->locale], true));

        abort_unless($targetLanguage && $targetLanguage->locale !== $languageContext['locale'], 422, '請選擇不同的目標語系');

        [$content, $source] = $this->ensureInfoContent($site->id, $languageContext['locale'], $module, $config);
        $target = $content->translations()->withTrashed()->where('locale', $targetLanguage->locale)->first();

        if ($target && empty($data['overwrite'])) {
            return response()->json([
                'ok' => false,
                'message' => "目標語系 ({$targetLanguage->name}) 已存在資料，請勾選「覆蓋已存在的資料」後重試",
                'needs_overwrite' => true,
            ], 409);
        }

        DB::transaction(function () use ($content, $source, $target, $targetLanguage): void {
            if ($target?->trashed()) {
                $target->restore();
            }

            $target = $target ?: $content->translations()->make(['locale' => $targetLanguage->locale]);
            $target->fill([
                'title' => $source->title,
                'slug' => $source->slug,
                'summary' => $source->summary,
                'body' => $source->body,
                'seo_title' => $source->seo_title,
                'seo_description' => $source->seo_description,
                'custom_fields' => $this->duplicateDynamicFiles($source->custom_fields ?? [], (int) $content->site_id),
            ]);
            $target->save();
        });

        return response()->json([
            'ok' => true,
            'message' => "資料已複製到 {$targetLanguage->name}",
            'redirect_url' => route('admin.info.edit', [$module, 'language' => $targetLanguage->slug]),
        ]);
    }

    private function moduleConfig(string $module): array
    {
        $config = CmsSetLoader::get($module, 'info');
        abort_unless($config, 404);

        return $config;
    }

    private function ensureInfoContent(int $siteId, string $locale, string $module, array $config): array
    {
        $type = ContentType::query()->updateOrCreate(
            ['site_id' => $siteId, 'code' => $config['content_type']],
            ['name' => $config['label'], 'is_active' => true, 'config' => ['page_type' => 'info', 'module' => $module]]
        );

        $content = Content::query()->firstOrCreate(
            ['site_id' => $siteId, 'content_type_id' => $type->id, 'sort_order' => 1],
            ['status' => 'published', 'published_at' => now()]
        );

        $translation = $content->translations()->firstOrCreate(
            ['locale' => $locale],
            [
                'title' => $config['label'],
                'slug' => $config['slug'],
                'summary' => null,
                'body' => null,
                'seo_title' => null,
                'seo_description' => null,
                'custom_fields' => [],
            ]
        );

        return [$content->load('translations'), $translation];
    }

    private function validatedData(Request $request, array $config): array
    {
        $rules = [];

        foreach ($this->fields($config) as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $target = $field['maps_to'] ?? $name;

            if ($type === 'image_upload') {
                $rules[$name] = ['nullable'];
                $rules["{$name}_title"] = ['nullable', 'array'];
                $rules["{$name}_title.*"] = ['nullable', 'string', 'max:255'];
                $rules["{$name}_update"] = ['nullable', 'array'];
                $rules["{$name}_update.*"] = ['nullable'];
                continue;
            }

            $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];
            $rule[] = match ($type) {
                'datetime' => 'date',
                default => 'string',
            };

            if ($type === 'select') {
                $rule[] = Rule::in(array_keys($field['options'] ?? []));
            } elseif ($type !== 'datetime') {
                $rule[] = 'max:5000';
            }

            $rules[$name] = $rule;

            if ($target !== $name) {
                $rules[$target] = ['nullable'];
            }
        }

        $validated = $request->validate($rules);
        $data = [];

        foreach ($this->fields($config) as $field) {
            $name = $field['name'];
            $target = $field['maps_to'] ?? $name;

            if (($field['type'] ?? null) === 'image_upload') {
                continue;
            }

            $value = $validated[$name] ?? ($field['default'] ?? null);
            if ($value === 'now') {
                $value = now()->format('Y-m-d H:i:s');
            }

            $data[$name] = $value;
            $data[$target] = $value;
        }

        return $data;
    }

    private function valuesFor(Content $content, $translation, array $config): array
    {
        $customFields = $translation->custom_fields ?? [];
        $values = [
            'title' => $translation->title,
            'summary' => $translation->summary,
            'body' => $translation->body,
            'seo_title' => $translation->seo_title,
            'seo_description' => $translation->seo_description,
            'status' => $content->status,
            'published_at' => $content->published_at,
        ];

        foreach ($this->fields($config) as $field) {
            $name = $field['name'];
            $target = $field['maps_to'] ?? $name;
            $values[$name] = $customFields[$name] ?? $values[$target] ?? ($field['default'] ?? null);
        }

        return $values;
    }

    private function fields(array $config): array
    {
        return collect($config['sections'] ?? [])
            ->flatMap(fn (array $section) => $section['fields'] ?? [])
            ->values()
            ->all();
    }

    private function mediaByRoleForItem(Content $content): array
    {
        return DB::table('content_media')
            ->join('media_files', 'media_files.id', '=', 'content_media.media_file_id')
            ->where('content_media.content_id', $content->id)
            ->orderBy('content_media.sort_order')
            ->select(['media_files.id', 'media_files.path', 'media_files.alt_text', 'content_media.role', 'content_media.sort_order'])
            ->get()
            ->groupBy('role')
            ->map(fn ($items) => $items->map(fn ($media) => [
                'id' => $media->id,
                'title' => $media->alt_text ?? '',
                'url' => Storage::disk('public')->url($media->path),
                'sort_order' => $media->sort_order,
            ])->all())
            ->all();
    }

    private function syncMediaUploads(Request $request, Content $content, array $config, int $siteId): void
    {
        foreach ($request->input('delete_file', []) as $mediaId) {
            DB::table('content_media')->where('content_id', $content->id)->where('media_file_id', (int) $mediaId)->delete();
        }

        foreach ($request->input('update_file_title', []) as $mediaId => $title) {
            DB::table('media_files')->where('id', (int) $mediaId)->update([
                'alt_text' => $title,
                'updated_at' => now(),
            ]);
        }

        foreach ($this->fields($config) as $field) {
            if (($field['type'] ?? null) !== 'image_upload') {
                continue;
            }

            $name = $field['name'];
            $role = $field['file_type'] ?? $name;
            $multiple = (bool) ($field['multiple'] ?? false);

            foreach ((array) $request->file("{$name}_update", []) as $mediaId => $file) {
                if ($file) {
                    $this->replaceMediaFile((int) $mediaId, $file, $siteId, $role, (string) $request->input("update_file_title.{$mediaId}", ''));
                }
            }

            $files = $request->file($name, []);
            if ($files instanceof \Illuminate\Http\UploadedFile) {
                $files = [$files];
            }

            if (!$multiple && !empty($files)) {
                DB::table('content_media')->where('content_id', $content->id)->where('role', $role)->delete();
            }

            foreach ((array) $files as $index => $file) {
                if (!$file) {
                    continue;
                }

                $mediaId = $this->storeMediaFile($file, $siteId, $role, (string) $request->input("{$name}_title.{$index}", ''));
                $sortOrder = ((int) DB::table('content_media')->where('content_id', $content->id)->where('role', $role)->max('sort_order')) + 1;

                DB::table('content_media')->insert([
                    'content_id' => $content->id,
                    'media_file_id' => $mediaId,
                    'role' => $role,
                    'sort_order' => $sortOrder,
                    'metadata' => null,
                ]);
            }
        }
    }

    private function storeMediaFile(\Illuminate\Http\UploadedFile $file, int $siteId, string $role, string $title): int
    {
        $path = $file->store("sites/{$siteId}/info/{$role}", 'public');
        [$width, $height] = $this->imageDimensions(Storage::disk('public')->path($path));

        return (int) DB::table('media_files')->insertGetId([
            'site_id' => $siteId,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $title,
            'metadata' => json_encode(['role' => $role], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function replaceMediaFile(int $mediaId, \Illuminate\Http\UploadedFile $file, int $siteId, string $role, string $title): void
    {
        $existing = DB::table('media_files')->where('id', $mediaId)->first();
        if (!$existing) {
            return;
        }

        $path = $file->store("sites/{$siteId}/info/{$role}", 'public');
        [$width, $height] = $this->imageDimensions(Storage::disk('public')->path($path));

        if ($existing->disk === 'public' && $existing->path) {
            Storage::disk('public')->delete($existing->path);
        }

        DB::table('media_files')->where('id', $mediaId)->update([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'alt_text' => $title,
            'updated_at' => now(),
        ]);
    }

    private function imageDimensions(string $path): array
    {
        $size = @getimagesize($path);

        return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
    }

    private function duplicateDynamicFiles(array $customFields, int $siteId): array
    {
        $duplicated = $customFields;

        $walker = function (&$value) use (&$walker, $siteId): void {
            if (!is_array($value)) {
                return;
            }

            if (!empty($value['path']) && is_string($value['path'])) {
                $disk = (string) ($value['disk'] ?? 'public');
                $value['path'] = $this->copyStoredFile($disk, $value['path'], "sites/{$siteId}/info/dynamic");
                $value['disk'] = $disk;

                if ($disk === 'public') {
                    $value['url'] = Storage::disk('public')->url($value['path']);
                }
            }

            foreach ($value as &$child) {
                $walker($child);
            }
        };

        $walker($duplicated);

        return $duplicated;
    }

    private function copyStoredFile(string $disk, string $path, ?string $targetDirectory = null): string
    {
        if ($path === '' || !Storage::disk($disk)->exists($path)) {
            throw ValidationException::withMessages([
                'copy' => "找不到要複製的檔案：{$path}",
            ]);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $directory = trim($targetDirectory ?: pathinfo($path, PATHINFO_DIRNAME), '.\\/');
        $filename = Str::uuid()->toString() . ($extension ? ".{$extension}" : '');
        $newPath = ($directory !== '' ? "{$directory}/" : '') . $filename;

        if (!Storage::disk($disk)->copy($path, $newPath)) {
            throw ValidationException::withMessages([
                'copy' => "檔案複製失敗：{$path}",
            ]);
        }

        return $newPath;
    }
}
