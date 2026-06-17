<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Language;
use App\Models\LanguagePack;
use App\Models\Product;
use App\Models\TaxonomyTerm;
use App\Services\TaxonomyTreeService;
use App\Support\AdminContext;
use App\Support\AdminLanguage;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function __construct(private readonly TaxonomyTreeService $treeService)
    {
    }

    public function index(AdminContext $context, Request $request, string $resource): View
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, $request);
        $config = $this->withRuntimeColumns($config, $languageContext);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : ($this->localeForConfig($config, $site));
        $keyword = trim((string) $request->query('search', ''));
        $termId = $request->integer('term_id') ?: null;
        $trash = $request->boolean('trash');
        $localizedTrash = $this->usesLocalizedTranslations($config);

        $query = $this->baseQuery($config, $site?->id, $locale, $trash && $localizedTrash, $trash && $localizedTrash);
        if ($trash) {
            $localizedTrash ? $query->withTrashed() : $query->onlyTrashed();
        }

        if ($keyword !== '') {
            $this->applySearch($query, $config, $keyword);
        }

        if ($termId && !empty($config['category_relation'])) {
            $this->applyTermScope($query, $config, $termId);
        }

        $sortScope = $this->baseQuery($config, $site?->id, $locale);
        if ($keyword !== '') {
            $this->applySearch($sortScope, $config, $keyword);
        }
        if ($termId && !empty($config['category_relation'])) {
            $this->applyTermScope($sortScope, $config, $termId);
        }

        if (!$trash && ($config['sort_column'] ?? null) === 'sort_order') {
            if ($termId && !empty($config['category_relation'])) {
                $this->normalizeResourceSortScope($config, $sortScope, (int) $site?->id, $termId);
                $this->applyResourceSortOrder($query, $config, (int) $site?->id, $termId);
            } else {
                $this->normalizeSortOrder($this->withoutPinned($this->baseQuery($config, $site?->id, $locale)));
            }
        }

        $table = $query->getModel()->getTable();
        $hasPinColumn = $this->hasPinColumn($query);
        $items = $query
            ->when($hasPinColumn, fn (Builder $q) => $q->orderByDesc("{$table}.is_pinned"))
            ->when(
                ($config['sort_column'] ?? null) === 'sort_order' && !$termId,
                fn (Builder $q) => $q->orderBy("{$table}.sort_order")
            )
            ->latest("{$table}.updated_at")
            ->paginate((int) $request->query('per_page', 12))
            ->withQueryString();

        $sortOptionCount = $this->withoutPinned($sortScope)->count();

        return view('admin.resources.index', $this->viewData($context, $resource, $config, [
            'items' => $items,
            'keyword' => $keyword,
            'termId' => $termId,
            'trash' => $trash,
            'trashCount' => $this->trashCount($config, $site?->id, $locale),
            'sortOptionCount' => $sortOptionCount,
            'taxonomyOptions' => $this->taxonomyOptions($config, $site, $locale),
            'taxonomyTreesByField' => $this->taxonomyTreesByField($config, $site, $locale),
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
        ]));
    }

    public function create(AdminContext $context, Request $request, string $resource): View
    {
        $config = $this->config($resource);
        abort_if(($config['show_add_button'] ?? true) === false, 404);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : ($this->localeForConfig($config, $site));
        $taxonomyOptions = $this->taxonomyOptions($config, $site, $locale);
        $taxonomyOptionsByField = $this->taxonomyOptionsByField($config, $site, $locale);
        $selectedTermIdsByField = $this->defaultSelectedTermIdsByField(
            $config,
            $taxonomyOptionsByField,
            $request->integer('term_id') ?: null
        );
        $selectedTermIds = collect($selectedTermIdsByField)->flatten()->filter()->values()->all();

        return view('admin.resources.form', $this->viewData($context, $resource, $config, [
            'item' => null,
            'values' => $this->emptyValues($config, $locale),
            'mediaByRole' => [],
            'taxonomyOptions' => $taxonomyOptions,
            'taxonomyOptionsByField' => $taxonomyOptionsByField,
            'taxonomyTreesByField' => $this->taxonomyTreesByField($config, $site, $locale),
            'selectedTermIds' => $selectedTermIds,
            'selectedTermIdsByField' => $selectedTermIdsByField,
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
        ]));
    }

    public function store(AdminContext $context, Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        abort_if(($config['show_add_button'] ?? true) === false, 404);
        $site = $context->site();
        abort_unless($site, 404);

        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $site->default_locale;
        $request->attributes->set('site_id', $site->id);
        $data = $this->validatedData($request, $config, $locale);

        DB::transaction(function () use ($request, $config, $site, $data): void {
            $item = match ($config['strategy']) {
                'content' => $this->storeContent($config, $site->id, $data),
                'product' => $this->storeProduct($site->id, $data),
                'language' => $this->storeLanguage($site->id, $data),
                'language_pack' => $this->storeLanguagePack($site->id, $data),
                default => throw new \InvalidArgumentException('不支援的資料儲存策略'),
            };

            $this->syncTerms($item, $config, $data);
            $this->syncMediaUploads($request, $item, $config, $site->id);
            $this->syncDynamicFields($request, $item, $config, $site->id, $data['locale']);
        });

        return redirect()
            ->route("admin.{$resource}.index", $this->languageRouteParams($languageContext, $languageEnabled))
            ->with('status', "{$config['label']}已新增");
    }

    public function edit(AdminContext $context, int $id, string $resource): View
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : ($this->localeForConfig($config, $site));
        $item = $this->findItem($config, $id, $site?->id, $locale);

        if ($item instanceof ContactMessage && !$item->is_read) {
            $item->forceFill(['is_read' => true])->save();
        }

        return view('admin.resources.form', $this->viewData($context, $resource, $config, [
            'item' => $item,
            'values' => $this->valuesForItem($item, $config, $locale),
            'mediaByRole' => $this->mediaByRoleForItem($item, $config),
            'taxonomyOptions' => $this->taxonomyOptions($config, $site, $locale),
            'taxonomyOptionsByField' => $this->taxonomyOptionsByField($config, $site, $locale),
            'taxonomyTreesByField' => $this->taxonomyTreesByField($config, $site, $locale),
            'selectedTermIds' => $this->selectedTermIds($item, $config),
            'selectedTermIdsByField' => $this->selectedTermIdsByField($item, $config),
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
        ]));
    }

    public function update(AdminContext $context, Request $request, int $id, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : ($this->localeForConfig($config, $site));
        $request->attributes->set('site_id', $site?->id);
        $item = $this->findItem($config, $id, $site?->id, $locale);
        $data = $this->validatedData($request, $config, $locale, $item);

        DB::transaction(function () use ($request, $item, $config, $site, $data, $locale): void {
            match ($config['strategy']) {
                'content' => $this->updateContent($item, $data, $locale),
                'product' => $this->updateProduct($item, $data, $locale),
                'contact' => $this->updateContact($item, $data),
                'language' => $this->updateLanguage($item, $data),
                'language_pack' => $this->updateLanguagePack($item, $data),
                default => throw new \InvalidArgumentException('不支援的資料儲存策略'),
            };

            $this->syncTerms($item, $config, $data);
            $this->syncMediaUploads($request, $item, $config, (int) $site?->id);
            $this->syncDynamicFields($request, $item, $config, (int) $site?->id, $data['locale']);
        });

        return redirect()
            ->route("admin.{$resource}.edit", [$item->id, ...$this->languageRouteParams($languageContext, $languageEnabled)])
            ->with('status', "{$config['label']}已更新");
    }

    public function dropzoneUpload(AdminContext $context, Request $request, int $id, string $resource): JsonResponse
    {
        $config = $this->config($resource);
        abort_unless(in_array($config['strategy'] ?? null, ['content', 'product'], true), 404);

        $site = $context->site();
        abort_unless($site, 404);

        $locale = $this->localeForConfig($config, $site, $request);
        $item = $this->findItem($config, $id, $site->id, $locale);
        $field = $this->dropzoneImageField(
            $config,
            (string) $request->input('field', ''),
            (string) $request->input('file_type', '')
        );

        abort_unless($field, 404);

        $file = $request->file('file');
        $validator = Validator::make(
            ['file' => $file],
            ['file' => ['required', 'file']],
            [],
            ['file' => $field['label'] ?? '圖片']
        );

        if ($file instanceof UploadedFile) {
            $this->validateUploadedFileByField($validator, 'file', $file, $field);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        /** @var UploadedFile $file */
        $role = (string) ($field['file_type'] ?? $field['name']);
        $mediaId = DB::transaction(function () use ($file, $site, $config, $item, $field, $role): int {
            if (empty($field['multiple'])) {
                $this->detachMediaByRole($item, $config, $role);
            }

            $mediaId = $this->storeMediaFile($file, $site->id, $config['strategy'], $role, '');
            $this->attachMedia($item, $config, $mediaId, $role);

            return $mediaId;
        });

        $media = DB::table('media_files')->where('id', $mediaId)->first();
        $mediaUrl = $media?->path ? Storage::disk('public')->url($media->path) : null;

        return response()->json([
            'status' => 'success',
            'message' => '上傳成功',
            'files' => [[
                'id' => $mediaId,
                'name' => $media?->original_name ?? $file->getClientOriginalName(),
                'link' => $mediaUrl,
                'url' => $mediaUrl,
                'file_type' => $role,
            ]],
        ]);
    }

    public function destroy(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $this->localeForConfig($config, $site);
        $item = $this->findItem($config, $id, $site?->id, $locale);

        DB::transaction(fn () => $this->deleteResourceItem($item, $config, $locale));
        $redirectUrl = route("admin.{$resource}.index", $this->languageRouteParams($languageContext, $languageEnabled));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已移到垃圾桶",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "{$config['label']}已移到垃圾桶");
    }

    public function restore(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $this->localeForConfig($config, $site);
        $item = $this->trashQuery($config, $site?->id, $locale)->whereKey($id)->firstOrFail();

        DB::transaction(fn () => $this->restoreResourceItem($item, $config, $locale));
        $redirectUrl = route("admin.{$resource}.index", $this->languageRouteParams($languageContext, $languageEnabled));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已還原",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "{$config['label']}已還原");
    }

    public function forceDelete(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $this->localeForConfig($config, $site);
        $item = $this->trashQuery($config, $site?->id, $locale)->whereKey($id)->firstOrFail();

        DB::transaction(fn () => $this->forceDeleteResourceItem($item, $config, $locale));
        $redirectUrl = route("admin.{$resource}.index", ['trash' => 1, ...$this->languageRouteParams($languageContext, $languageEnabled)]);

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已永久刪除",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "{$config['label']}已永久刪除");
    }

    public function toggleStatus(AdminContext $context, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $this->localeForConfig($config, $site));
        $booleanStatusColumn = collect($config['list_columns'] ?? [])
            ->first(fn (array $column) => ($column['type'] ?? null) === 'boolean_status'
                && array_key_exists((string) ($column['key'] ?? ''), $item->getAttributes()));

        if ($booleanStatusColumn) {
            $key = (string) $booleanStatusColumn['key'];
            $nextValue = !$item->{$key};
            $item->forceFill([$key => $nextValue])->save();

            return response()->json([
                'status' => $nextValue ? 1 : 0,
                'label' => $nextValue ? '顯示' : '不顯示',
                'color' => $nextValue ? '#28a745' : '#dc3545',
            ]);
        }

        $options = array_keys($config['status_options'] ?? []);
        abort_if($options === [] || !isset($item->status), 404);

        $currentIndex = array_search($item->status, $options, true);
        $nextStatus = $options[((int) ($currentIndex === false ? 0 : $currentIndex) + 1) % count($options)];

        $item->forceFill([
            'status' => $nextStatus,
            'published_at' => in_array($nextStatus, ['published', 'active'], true) ? ($item->published_at ?? now()) : $item->published_at,
        ])->save();

        return response()->json([
            'status' => $nextStatus,
            'label' => $config['status_options'][$nextStatus] ?? $nextStatus,
            'color' => $this->statusColor($nextStatus),
        ]);
    }

    public function togglePin(AdminContext $context, Request $request, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $this->localeForConfig($config, $site));
        abort_unless(array_key_exists('is_pinned', $item->getAttributes()), 404);

        $data = $request->validate([
            'term_id' => ['nullable', 'integer', 'exists:taxonomy_terms,id'],
        ]);

        DB::transaction(function () use ($config, $site, $item, $data): void {
            $item->forceFill(['is_pinned' => !$item->is_pinned])->save();

            $locale = $this->localeForConfig($config, $site);
            $this->normalizeSortOrder($this->withoutPinned($this->baseQuery($config, $site?->id, $locale)));

            if (!empty($config['category_relation'])) {
                $termId = (int) ($data['term_id'] ?? 0);
                if ($termId > 0) {
                    $scope = $this->baseQuery($config, $site?->id, $locale);
                    $this->applyTermScope($scope, $config, $termId);
                    $this->normalizeResourceSortScope($config, $scope, (int) $site?->id, $termId);
                }

                $this->normalizeExistingResourceSortScopes($config, (int) $site?->id, $locale);
            }
        });

        $item->refresh();

        return response()->json(['is_pinned' => (bool) $item->is_pinned]);
    }

    public function sort(AdminContext $context, Request $request, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $this->localeForConfig($config, $site));
        $column = $config['sort_column'] ?? 'sort_order';
        abort_unless($column === 'sort_order' && array_key_exists('sort_order', $item->getAttributes()), 404);

        $data = $request->validate([
            'sort_order' => ['required', 'integer', 'min:1'],
            'term_id' => ['nullable', 'integer', 'exists:taxonomy_terms,id'],
        ]);

        DB::transaction(function () use ($config, $site, $item, $data): void {
            $termId = (int) ($data['term_id'] ?? 0);
            abort_if((bool) ($item->is_pinned ?? false), 422, '置頂資料不參與一般排序，請先取消置頂。');

            if ($termId && !empty($config['category_relation'])) {
                $scope = $this->baseQuery($config, $site?->id, $this->localeForConfig($config, $site));
                $this->applyTermScope($scope, $config, $termId);
                $this->reorderResourceSortScope($config, $scope, $item, (int) $site?->id, $termId, (int) $data['sort_order']);
                return;
            }

            $this->reorderItems(
                $this->withoutPinned($this->baseQuery($config, $site?->id, $this->localeForConfig($config, $site))),
                $item,
                (int) $data['sort_order']
            );
        });

        $item->refresh();

        return response()->json(['sort_order' => $item->sort_order]);
    }

    public function bulkAction(AdminContext $context, Request $request, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        abort_unless($site, 404);

        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = AdminLanguage::enabledFor($config);
        $locale = $languageEnabled ? $languageContext['locale'] : $this->localeForConfig($config, $site, $request);

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete', 'restore', 'force_delete', 'clone', 'clone_local'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'target_language' => ['nullable', 'string', 'max:40'],
        ]);

        $trashAction = in_array($data['action'], ['restore', 'force_delete'], true);
        $itemsQuery = $trashAction
            ? $this->trashQuery($config, $site->id, $locale)
            : $this->baseQuery($config, $site->id, $locale);

        $items = $itemsQuery->whereKey($data['ids'])->get();
        $targetLocale = null;
        $targetLanguageSlug = null;
        if ($data['action'] === 'clone') {
            $targetLanguage = AdminLanguage::activeLanguages($site)
                ->first(fn (Language $language) => in_array($data['target_language'] ?? null, [$language->slug, $language->locale], true));
            $targetLocale = $targetLanguage?->locale;
            $targetLanguageSlug = $targetLanguage?->slug;

            if (!$targetLocale || $targetLocale === $locale) {
                return response()->json([
                    'message' => '請選擇不同的目標語系',
                ], 422);
            }
        }

        DB::transaction(function () use ($items, $config, $data, $locale, $targetLocale): void {
            if ($data['action'] === 'delete') {
                $items->each(fn (Model $item) => $this->deleteResourceItem($item, $config, $locale));
                return;
            }

            if ($data['action'] === 'restore') {
                $items->each(fn (Model $item) => $this->restoreResourceItem($item, $config, $locale));
                return;
            }

            if ($data['action'] === 'force_delete') {
                $items->each(fn (Model $item) => $this->forceDeleteResourceItem($item, $config, $locale));
                return;
            }

            abort_if(in_array($config['strategy'], ['contact', 'language', 'language_pack'], true), 422, '此模組不支援批次複製');

            foreach ($items as $item) {
                if ($data['action'] === 'clone' && $targetLocale) {
                    $this->cloneTranslationToLocale($item, $config, $locale, (string) $targetLocale);
                    continue;
                }

                $this->cloneItem($item, $config, $locale);
            }
        });

        return response()->json([
            'message' => $this->bulkActionMessage($data['action'], $items->count(), $config['label']),
            'redirect_url' => match ($data['action']) {
                'restore' => route("admin.{$resource}.index", $this->languageRouteParams($languageContext, $languageEnabled)),
                'clone' => route("admin.{$resource}.index", $languageEnabled ? ['language' => $targetLanguageSlug] : []),
                default => null,
            },
        ]);
    }

    private function bulkActionMessage(string $action, int $count, string $label): string
    {
        return match ($action) {
            'delete' => "已刪除 {$count} 筆{$label}。",
            'restore' => "已還原 {$count} 筆{$label}。",
            'force_delete' => "已永久刪除 {$count} 筆{$label}。",
            'clone', 'clone_local' => "已複製 {$count} 筆{$label}。",
            default => "已處理 {$count} 筆{$label}。",
        };
    }

    private function deleteResourceItem(Model $item, array $config, string $locale): void
    {
        if (in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            $translation = $this->localizedTranslation($item, $locale);
            $translation?->delete();

            return;
        }

        $item->delete();
    }

    private function restoreResourceItem(Model $item, array $config, string $locale): void
    {
        if (in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            if (method_exists($item, 'trashed') && $item->trashed()) {
                $item->restore();
            }

            $translation = $this->localizedTranslation($item, $locale, withTrashed: true, onlyTrashed: true);
            $translation?->restore();

            return;
        }

        $item->restore();
    }

    private function forceDeleteResourceItem(Model $item, array $config, string $locale): void
    {
        if (in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            $translation = $this->localizedTranslation($item, $locale, withTrashed: true, onlyTrashed: true);
            $translation?->forceDelete();

            if ($this->totalTranslationCount($item) === 0) {
                $this->detachItemRelationsBeforeForceDelete($item, $config);
                $item->forceDelete();
            }

            return;
        }

        $this->detachItemRelationsBeforeForceDelete($item, $config);
        $item->forceDelete();
    }

    private function localizedTranslation(Model $item, string $locale, bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        if (!method_exists($item, 'translations')) {
            return null;
        }

        $query = $item->translations();
        if ($withTrashed || $onlyTrashed) {
            $query->withTrashed();
        }

        $query->where('locale', $locale);

        if ($onlyTrashed) {
            $query->whereNotNull($query->getModel()->getQualifiedDeletedAtColumn());
        }

        return $query->first();
    }

    private function activeTranslationCount(Model $item): int
    {
        return method_exists($item, 'translations') ? $item->translations()->count() : 0;
    }

    private function totalTranslationCount(Model $item): int
    {
        return method_exists($item, 'translations') ? $item->translations()->withTrashed()->count() : 0;
    }

    private function detachItemRelationsBeforeForceDelete(Model $item, array $config): void
    {
        if ($item instanceof Content) {
            foreach ($item->translations as $translation) {
                foreach ($this->dynamicFilePaths($translation->custom_fields ?? []) as $path) {
                    Storage::disk('public')->delete($path);
                }
            }

            DB::table('content_term')->where('content_id', $item->id)->delete();
            DB::table('content_media')->where('content_id', $item->id)->delete();
            return;
        }

        if ($item instanceof Product) {
            DB::table('product_category_product')->where('product_id', $item->id)->delete();
            DB::table('product_media')->where('product_id', $item->id)->delete();
            return;
        }

        if ($item instanceof ContactMessage) {
            return;
        }
    }

    private function config(string $resource): array
    {
        $config = CmsSetLoader::get($resource, 'list');
        abort_unless($config, 404);

        return $config;
    }

    private function withRuntimeColumns(array $config, array $languageContext): array
    {
        if (($config['strategy'] ?? null) !== 'language_pack') {
            return $config;
        }

        $baseColumns = collect($config['list_columns'] ?? [])
            ->reject(fn (array $column) => ($column['type'] ?? null) === 'language_pack_values')
            ->values();

        $languageColumns = collect($languageContext['languages'] ?? [])
            ->map(fn (Language $language) => [
                'key' => 'language_pack_' . $language->locale,
                'label' => $language->name,
                'type' => 'language_pack_value',
                'locale' => $language->locale,
                'width' => 160,
            ]);

        $config['list_columns'] = $baseColumns
            ->concat($languageColumns)
            ->values()
            ->all();

        return $config;
    }

    private function baseQuery(array $config, ?int $siteId, string $locale, bool $withTrashedTranslations = false, bool $onlyTrashedTranslations = false): Builder
    {
        /** @var class-string<Model> $model */
        $model = $config['model'];
        $table = (new $model)->getTable();

        return $model::query()
            ->where("{$table}.site_id", $siteId)
            ->when($config['strategy'] === 'content', function (Builder $query) use ($config, $siteId, $locale, $withTrashedTranslations, $onlyTrashedTranslations): void {
                $typeId = ContentType::query()
                    ->where('site_id', $siteId)
                    ->where('code', $config['content_type'])
                    ->value('id');
                $query->where('content_type_id', $typeId)
                    ->whereHas('translations', fn (Builder $translation) => $this->applyTranslationLocaleScope($translation, $locale, $withTrashedTranslations, $onlyTrashedTranslations))
                    ->with(['translations' => fn ($translation) => $this->applyTranslationLocaleScope($translation, $locale, $withTrashedTranslations, $onlyTrashedTranslations), 'terms.parent']);
            })
            ->when($config['strategy'] === 'product', fn (Builder $query) => $query
                ->whereHas('translations', fn (Builder $translation) => $this->applyTranslationLocaleScope($translation, $locale, $withTrashedTranslations, $onlyTrashedTranslations))
                ->with(['translations' => fn ($translation) => $this->applyTranslationLocaleScope($translation, $locale, $withTrashedTranslations, $onlyTrashedTranslations), 'categories.parent']))
            ->when($config['strategy'] === 'contact', fn (Builder $query) => $query
                ->where('type', 'contact')
                ->when(
                    \Illuminate\Support\Facades\Schema::hasColumn($table, 'locale'),
                    fn (Builder $contactQuery) => $contactQuery->where("{$table}.locale", $locale)
                ))
            ->when($config['strategy'] === 'language_pack', fn (Builder $query) => $query->with('translations'));
    }

    private function applyTranslationLocaleScope($query, string $locale, bool $withTrashed, bool $onlyTrashed)
    {
        if ($withTrashed || $onlyTrashed) {
            $query->withTrashed();
        }

        $query->where('locale', $locale);

        if ($onlyTrashed) {
            $query->whereNotNull($query->getModel()->getQualifiedDeletedAtColumn());
        }

        return $query;
    }

    private function trashQuery(array $config, ?int $siteId, string $locale): Builder
    {
        if ($this->usesLocalizedTranslations($config)) {
            return $this->baseQuery($config, $siteId, $locale, true, true)->withTrashed();
        }

        return $this->baseQuery($config, $siteId, $locale)->onlyTrashed();
    }

    private function trashCount(array $config, ?int $siteId, string $locale): int
    {
        return (clone $this->trashQuery($config, $siteId, $locale))->count();
    }

    private function usesLocalizedTranslations(array $config): bool
    {
        return in_array($config['strategy'] ?? null, ['content', 'product'], true);
    }

    private function findItem(array $config, int $id, ?int $siteId, string $locale): Model
    {
        return $this->baseQuery($config, $siteId, $locale)->whereKey($id)->firstOrFail();
    }

    private function applySearch(Builder $query, array $config, string $keyword): void
    {
        match ($config['strategy']) {
            'content' => $query->whereHas('translations', fn (Builder $q) => $q
                ->where('title', 'like', "%{$keyword}%")
                ->orWhere('summary', 'like', "%{$keyword}%")),
            'product' => $query->where(function (Builder $q) use ($keyword): void {
                $q->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('translations', fn (Builder $translation) => $translation
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('summary', 'like', "%{$keyword}%"));
            }),
            'contact' => $query->where(function (Builder $q) use ($keyword): void {
                $q->where('subject', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            }),
            default => null,
        };
    }

    private function validatedData(Request $request, array $config, string $locale, ?Model $item = null): array
    {
        if (($config['strategy'] ?? null) === 'language') {
            $siteId = (int) $request->attributes->get('site_id', $item?->site_id ?? 0);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100'],
                'name_en' => ['nullable', 'string', 'max:100'],
                'slug' => ['required', 'string', 'max:40', Rule::unique('languages', 'slug')->where('site_id', $siteId)->ignore($item?->id)],
                'locale' => ['required', 'string', 'max:40', Rule::unique('languages', 'locale')->where('site_id', $siteId)->ignore($item?->id)],
                'is_default' => ['nullable'],
                'is_active' => ['nullable'],
            ]);

            $data['is_default'] = $request->boolean('is_default');
            $data['is_active'] = $request->boolean('is_active', true);

            return $data;
        }

        if (($config['strategy'] ?? null) === 'language_pack') {
            $siteId = (int) $request->attributes->get('site_id', $item?->site_id ?? 0);
            $data = $request->validate([
                'key' => ['required', 'string', 'max:190', Rule::unique('language_packs', 'key')->where('site_id', $siteId)->ignore($item?->id)],
                'note' => ['nullable', 'string', 'max:500'],
                'translations' => ['nullable', 'array'],
                'translations.*' => ['nullable', 'string', 'max:10000'],
            ]);

            $data['locale'] = $locale;

            return $data;
        }

        $rules = [
            'locale' => ['nullable', 'string', 'max:20'],
        ];

        foreach ($this->taxonomyFields($config) as $field) {
            if (!empty($field['multiple'])) {
                $rules[$field['name']] = [($field['required'] ?? false) ? 'required' : 'nullable', 'array'];
                $rules["{$field['name']}.*"] = ['integer', 'exists:taxonomy_terms,id'];
                continue;
            }

            $rules[$field['name']] = [($field['required'] ?? false) ? 'required' : 'nullable', 'integer', 'exists:taxonomy_terms,id'];
        }

        foreach ($config['form_sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $name = $field['name'];
                if (
                    ($field['readonly'] ?? false)
                    || in_array($field['type'], ['taxonomy', 'linked_taxonomy', 'image_upload', 'file_upload', 'dynamic_fields', 'updatetime'], true)
                ) {
                    if (($field['type'] ?? null) === 'dynamic_fields') {
                        $rules[$name] = [($field['required'] ?? false) ? 'required' : 'nullable', 'array'];
                        foreach (($field['fields'] ?? []) as $subField) {
                            $subName = $subField['name'] ?? null;
                            if (!$subName) {
                                continue;
                            }

                            $subType = $subField['type'] ?? 'text';
                            if (in_array($subType, ['image', 'image_upload', 'file', 'file_upload'], true)) {
                                $rules["{$name}.*.{$subName}"] = ['nullable'];
                                $rules["{$name}.*.{$subName}._existing"] = ['nullable', 'string'];
                                $rules["{$name}.*.{$subName}._title"] = ['nullable', 'string', 'max:255'];
                                continue;
                            }

                            $baseRule = !empty($subField['required']) ? 'required' : 'nullable';
                            $rules["{$name}.*.{$subName}"] = match ($subType) {
                                'number' => [$baseRule, 'numeric'],
                                'select' => [$baseRule, 'string', 'max:500'],
                                'textarea' => [$baseRule, 'string', 'max:10000'],
                                default => [$baseRule, 'string', 'max:5000'],
                            };
                        }
                    }

                    continue;
                }

                $rules[$name] = match ($field['type']) {
                    'number' => [($field['required'] ?? false) ? 'required' : 'nullable', 'numeric'],
                    'checkbox' => ['nullable'],
                    'datetime' => ['nullable', 'date'],
                    'select' => [($field['required'] ?? false) ? 'required' : 'nullable', 'string', Rule::in(array_keys($field['options'] ?? $config['status_options'] ?? []))],
                    default => [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:5000'],
                };
            }
        }

        $validator = Validator::make($request->all(), $rules, [], $this->validationAttributes($config));
        $validator->after(function ($validator) use ($request, $config, $item): void {
            $this->validateRequiredMediaUploads($validator, $request, $config, $item);
            $this->validateUploadedMediaConstraints($validator, $request, $config);
            $this->validateRequiredDynamicUploads($validator, $request, $config);
        });

        $data = $validator->validate();
        $data['locale'] = $data['locale'] ?? $locale;

        foreach ($config['form_sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (($field['type'] ?? null) === 'checkbox') {
                    $data[$field['name']] = $request->boolean($field['name']);
                }
            }
        }

        return $data;
    }

    private function validateRequiredMediaUploads(\Illuminate\Validation\Validator $validator, Request $request, array $config, ?Model $item): void
    {
        if (!in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            return;
        }

        $deletedIds = collect((array) $request->input('delete_file', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        foreach ($this->imageUploadFields($config) as $field) {
            if (empty($field['required'])) {
                continue;
            }

            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            $role = $field['file_type'] ?? $fieldName;
            $newUploadCount = count($this->uploadedFilesFrom($request->file($fieldName, [])))
                + count($this->uploadedFilesFrom($request->file("{$fieldName}_update", [])));
            $existingCount = $item ? $this->existingMediaCount($item, $config, (string) $role, $deletedIds) : 0;

            if ($newUploadCount === 0 && $existingCount === 0) {
                $label = $field['label'] ?? $fieldName;
                $validator->errors()->add($fieldName, "{$label}為必填欄位，請先上傳圖片。");
            }
        }
    }

    private function validateUploadedMediaConstraints(\Illuminate\Validation\Validator $validator, Request $request, array $config): void
    {
        if (!in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            return;
        }

        foreach ($this->imageUploadFields($config) as $field) {
            $fieldName = $field['name'] ?? null;
            if (!$fieldName) {
                continue;
            }

            foreach ($this->uploadedFilesFrom($request->file($fieldName, [])) as $file) {
                $this->validateUploadedFileByField($validator, $fieldName, $file, $field);
            }

            foreach ($this->uploadedFilesFrom($request->file("{$fieldName}_update", [])) as $file) {
                $this->validateUploadedFileByField($validator, "{$fieldName}_update", $file, $field);
            }
        }
    }

    private function validateRequiredDynamicUploads(\Illuminate\Validation\Validator $validator, Request $request, array $config): void
    {
        foreach ($config['form_sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['type'] ?? null) !== 'dynamic_fields') {
                    continue;
                }

                $fieldName = $field['name'] ?? null;
                if (!$fieldName) {
                    continue;
                }

                $inputRows = $request->input($fieldName, []);
                $fileRows = $request->file($fieldName, []);
                if (!is_array($inputRows)) {
                    if (!empty($field['required'])) {
                        $label = $field['label'] ?? $fieldName;
                        $validator->errors()->add($fieldName, "{$label}至少需要新增一個項目。");
                    }
                    continue;
                }

                $meaningfulRows = 0;
                $rowIndexes = collect(array_keys((array) $inputRows))
                    ->merge(array_keys((array) $fileRows))
                    ->unique()
                    ->sortBy(fn ($index) => (int) $index)
                    ->values();

                foreach ($rowIndexes as $rowIndex) {
                    $row = (array) data_get($inputRows, $rowIndex, []);
                    $fileRow = data_get($fileRows, $rowIndex, []);
                    $rowHasValue = $this->dynamicRequestRowHasValue($row, $fileRow);
                    if ($rowHasValue) {
                        $meaningfulRows++;
                    }

                    foreach (($field['fields'] ?? []) as $subField) {
                        $subName = $subField['name'] ?? null;
                        $subType = $subField['type'] ?? 'text';
                        if (!$subName || !in_array($subType, ['image', 'image_upload', 'file', 'file_upload'], true)) {
                            continue;
                        }

                        $path = "{$fieldName}.{$rowIndex}.{$subName}";
                        foreach ($this->uploadedFilesFrom(data_get($fileRows, "{$rowIndex}.{$subName}")) as $file) {
                            $this->validateUploadedFileByField($validator, $path, $file, $subField);
                        }

                        if (empty($subField['required'])) {
                            continue;
                        }

                        if (!$rowHasValue) {
                            continue;
                        }

                        $hasUpload = count($this->uploadedFilesFrom(data_get($fileRows, "{$rowIndex}.{$subName}"))) > 0;
                        $hasExisting = filled(data_get($row, "{$subName}._existing"));

                        if (!$hasUpload && !$hasExisting) {
                            $label = $subField['label'] ?? $subName;
                            $validator->errors()->add($path, "{$label}為必填欄位，請先上傳檔案。");
                        }
                    }
                }

                if (!empty($field['required']) && $meaningfulRows === 0) {
                    $label = $field['label'] ?? $fieldName;
                    $validator->errors()->add($fieldName, "{$label}至少需要新增一個項目。");
                }
            }
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedFilesFrom(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (!is_array($files)) {
            return [];
        }

        return collect($files)
            ->flatMap(fn ($file) => $this->uploadedFilesFrom($file))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $deletedIds
     */
    private function existingMediaCount(Model $item, array $config, string $role, array $deletedIds = []): int
    {
        $pivotTable = ($config['strategy'] ?? null) === 'content' ? 'content_media' : 'product_media';
        $foreignKey = ($config['strategy'] ?? null) === 'content' ? 'content_id' : 'product_id';

        return (int) DB::table($pivotTable)
            ->where($foreignKey, $item->getKey())
            ->where('role', $role)
            ->when($deletedIds !== [], fn ($query) => $query->whereNotIn('media_file_id', $deletedIds))
            ->count();
    }

    private function validateUploadedFileByField(\Illuminate\Validation\Validator $validator, string $errorKey, UploadedFile $file, array $field): void
    {
        $label = $field['label'] ?? $field['name'] ?? $errorKey;

        if (!$file->isValid()) {
            $validator->errors()->add($errorKey, "{$label}上傳失敗，請重新選擇檔案。");
            return;
        }

        $fieldType = $field['type'] ?? 'file';
        if (in_array($fieldType, ['image', 'image_upload'], true) && !str_starts_with((string) $file->getMimeType(), 'image/')) {
            $validator->errors()->add($errorKey, "{$label}必須是圖片檔。");
        }

        $maxSizeMb = $this->fieldMaxSizeMb($field);
        if ($maxSizeMb && $file->getSize() > ($maxSizeMb * 1024 * 1024)) {
            $validator->errors()->add($errorKey, "{$label}檔案大小不可超過 {$maxSizeMb}MB。");
        }

        $allowed = $this->fieldAllowedExtensions($field);
        if ($allowed !== [] && !in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
            $validator->errors()->add($errorKey, "{$label}檔案格式必須是 " . implode(', ', $allowed) . '。');
        }
    }

    private function fieldMaxSizeMb(array $field): ?int
    {
        $maxSize = $field['maxSize'] ?? data_get($field, 'size.maxSize');
        if (!is_numeric($maxSize) && in_array($field['type'] ?? null, ['image', 'image_upload'], true)) {
            $maxSize = 2;
        }

        return is_numeric($maxSize) && (int) $maxSize > 0 ? (int) $maxSize : null;
    }

    /**
     * @return array<int, string>
     */
    private function fieldAllowedExtensions(array $field): array
    {
        $format = $field['format'] ?? null;
        if (!is_string($format) || trim($format) === '') {
            return [];
        }

        return collect(explode(',', $format))
            ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
            ->filter()
            ->values()
            ->all();
    }

    private function dynamicRequestRowHasValue(array $row, mixed $fileRow): bool
    {
        foreach ($row as $key => $value) {
            if ($key === '_uid') {
                continue;
            }

            if ($this->submittedValueHasContent($value)) {
                return true;
            }
        }

        return count($this->uploadedFilesFrom($fileRow)) > 0;
    }

    private function submittedValueHasContent(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                if ($this->submittedValueHasContent($nestedValue)) {
                    return true;
                }
            }

            return false;
        }

        return is_string($value) ? trim($value) !== '' : filled($value);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(array $config): array
    {
        $attributes = [
            'locale' => '語系',
        ];

        foreach ($this->taxonomyFields($config) as $field) {
            if (!empty($field['name'])) {
                $attributes[$field['name']] = $field['label'] ?? $field['name'];
            }
        }

        foreach ($config['form_sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $name = $field['name'] ?? null;
                if (!$name) {
                    continue;
                }

                $attributes[$name] = $field['label'] ?? $name;

                if (($field['type'] ?? null) !== 'dynamic_fields') {
                    continue;
                }

                foreach (($field['fields'] ?? []) as $subField) {
                    $subName = $subField['name'] ?? null;
                    if (!$subName) {
                        continue;
                    }

                    $attributes["{$name}.*.{$subName}"] = $subField['label'] ?? $subName;
                }
            }
        }

        return $attributes;
    }

    private function storeContent(array $config, int $siteId, array $data): Content
    {
        $type = ContentType::query()->where('site_id', $siteId)->where('code', $config['content_type'])->firstOrFail();
        $status = $data['status'] ?? 'draft';
        $content = Content::query()->create([
            'site_id' => $siteId,
            'content_type_id' => $type->id,
            'status' => $status,
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder(Content::class, [
                'site_id' => $siteId,
                'content_type_id' => $type->id,
            ])),
            'published_at' => $data['published_at'] ?? ($status === 'published' ? now() : null),
        ]);

        $content->translations()->create([
            'locale' => $data['locale'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'], $data['locale'], 'content'),
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);

        return $content;
    }

    private function updateContent(Content $content, array $data, string $locale): void
    {
        $status = $data['status'] ?? $content->status;
        $content->update([
            'status' => $status,
            'is_pinned' => array_key_exists('is_pinned', $data) ? (bool) $data['is_pinned'] : $content->is_pinned,
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $content->sort_order,
            'published_at' => $data['published_at'] ?? ($status === 'published' ? ($content->published_at ?? now()) : null),
        ]);

        $translation = $content->translations()->where('locale', $data['locale'])->first();
            $translation?->update([
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'], $data['locale'], 'content', $translation->id),
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]) ?? $content->translations()->create([
            'locale' => $data['locale'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'], $data['locale'], 'content'),
            'summary' => $data['summary'] ?? null,
            'body' => $data['body'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);
    }

    private function storeProduct(int $siteId, array $data): Product
    {
        $status = $data['status'] ?? 'draft';
        $product = Product::query()->create([
            'site_id' => $siteId,
            'sku' => $data['sku'] ?? null,
            'status' => $status,
            'product_type' => 'simple',
            'base_price' => $data['base_price'] ?? 0,
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'currency_code' => 'TWD',
            'is_taxable' => (bool) ($data['is_taxable'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $this->nextSortOrder(Product::class, ['site_id' => $siteId])),
            'published_at' => $data['published_at'] ?? ($status === 'active' ? now() : null),
        ]);

        $product->translations()->create([
            'locale' => $data['locale'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name'], $data['locale'], 'product'),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);

        return $product;
    }

    private function updateProduct(Product $product, array $data, string $locale): void
    {
        $status = $data['status'] ?? $product->status;
        $product->update([
            'sku' => $data['sku'] ?? null,
            'status' => $status,
            'base_price' => $data['base_price'] ?? 0,
            'compare_at_price' => $data['compare_at_price'] ?? null,
            'cost_price' => $data['cost_price'] ?? null,
            'is_taxable' => (bool) ($data['is_taxable'] ?? false),
            'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : $product->sort_order,
            'published_at' => $data['published_at'] ?? ($status === 'active' ? ($product->published_at ?? now()) : null),
        ]);

        $translation = $product->translations()->where('locale', $data['locale'])->first();
        $translation?->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name'], $data['locale'], 'product', $translation->id),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]) ?? $product->translations()->create([
            'locale' => $data['locale'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name'], $data['locale'], 'product'),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ]);
    }

    private function updateContact(ContactMessage $message, array $data): void
    {
        $message->update([
            'locale' => $data['locale'] ?? $message->locale,
            'status' => $data['status'] ?? $message->status,
            'is_read' => (bool) ($data['is_read'] ?? false),
            'admin_note' => $data['admin_note'] ?? null,
            'handled_at' => now(),
        ]);
    }

    private function storeLanguage(int $siteId, array $data): Language
    {
        $language = Language::query()->create([
            'site_id' => $siteId,
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? null,
            'slug' => $data['slug'],
            'locale' => $data['locale'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort_order' => $this->nextSortOrder(Language::class, ['site_id' => $siteId]),
        ]);

        if ($language->is_default) {
            $this->setDefaultLanguage($language);
        }

        return $language;
    }

    private function updateLanguage(Language $language, array $data): void
    {
        $language->update([
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? null,
            'slug' => $data['slug'],
            'locale' => $data['locale'],
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($language->is_default) {
            $this->setDefaultLanguage($language);
        }
    }

    private function setDefaultLanguage(Language $language): void
    {
        Language::query()
            ->where('site_id', $language->site_id)
            ->whereKeyNot($language->id)
            ->update(['is_default' => false]);

        $language->site?->update(['default_locale' => $language->locale]);
    }

    private function storeLanguagePack(int $siteId, array $data): LanguagePack
    {
        $pack = LanguagePack::query()->create([
            'site_id' => $siteId,
            'key' => $data['key'],
            'note' => $data['note'] ?? null,
            'sort_order' => $this->nextSortOrder(LanguagePack::class, ['site_id' => $siteId]),
        ]);

        $this->syncLanguagePackTranslations($pack, $data['translations'] ?? []);

        return $pack;
    }

    private function updateLanguagePack(LanguagePack $pack, array $data): void
    {
        $pack->update([
            'key' => $data['key'],
            'note' => $data['note'] ?? null,
        ]);

        $this->syncLanguagePackTranslations($pack, $data['translations'] ?? []);
    }

    private function syncLanguagePackTranslations(LanguagePack $pack, array $translations): void
    {
        foreach ($translations as $locale => $value) {
            $pack->translations()->updateOrCreate(
                ['locale' => (string) $locale],
                ['value' => $value]
            );
        }
    }

    private function syncTerms(Model $item, array $config, array $data): void
    {
        if (empty($config['category_relation'])) {
            return;
        }

        $termIds = collect($this->taxonomyFields($config))
            ->flatMap(fn (array $field) => Arr::wrap($data[$field['name']] ?? []))
            ->filter()
            ->unique()
            ->values();

        $payload = $termIds
            ->filter()
            ->values()
            ->mapWithKeys(fn ($termId, $index) => [(int) $termId => ['sort_order' => $index + 1]])
            ->all();

        $item->{$config['category_relation']}()->sync($payload);
    }

    private function cloneItem(Model $item, array $config, string $locale): void
    {
        if ($item instanceof Content) {
            $translation = $item->translation($locale);
            $clone = $item->replicate(['published_at']);
            $clone->status = 'draft';
            $clone->is_pinned = false;
            $clone->published_at = null;
            $clone->sort_order = ((int) Content::query()
                ->where('site_id', $item->site_id)
                ->where('content_type_id', $item->content_type_id)
                ->max('sort_order')) + 1;
            $clone->save();

            if ($translation) {
                $clone->translations()->create([
                    'locale' => $translation->locale,
                    'title' => $translation->title . ' Copy',
                    'slug' => $this->uniqueSlug($translation->slug . '-copy', $translation->locale, 'content'),
                    'summary' => $translation->summary,
                    'body' => $translation->body,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                    'custom_fields' => $this->duplicateDynamicFiles($translation->custom_fields ?? [], (int) $item->site_id, 'content'),
                ]);
            }

            $clone->terms()->sync($item->terms->pluck('id')->all());
            $this->duplicateMediaRelations($item, $clone, $config);
            return;
        }

        if ($item instanceof Product) {
            $translation = $item->translation($locale);
            $clone = $item->replicate(['published_at']);
            $clone->status = 'draft';
            $clone->published_at = null;
            $clone->sort_order = ((int) Product::query()->where('site_id', $item->site_id)->max('sort_order')) + 1;
            $clone->save();

            if ($translation) {
                $clone->translations()->create([
                    'locale' => $translation->locale,
                    'name' => $translation->name . ' Copy',
                    'slug' => $this->uniqueSlug($translation->slug . '-copy', $translation->locale, 'product'),
                    'summary' => $translation->summary,
                    'description' => $translation->description,
                    'seo_title' => $translation->seo_title,
                    'seo_description' => $translation->seo_description,
                ]);
            }

            $clone->categories()->sync($item->categories->pluck('id')->all());
            $this->duplicateMediaRelations($item, $clone, $config);
        }
    }

    private function cloneTranslationToLocale(Model $item, array $config, string $sourceLocale, string $targetLocale): void
    {
        if ($item instanceof Content) {
            $source = $item->translation($sourceLocale);
            if (!$source) {
                return;
            }

            $clone = $item->replicate();
            $clone->is_pinned = false;
            $clone->view_count = 0;
            $clone->sort_order = ((int) Content::query()
                ->where('site_id', $item->site_id)
                ->where('content_type_id', $item->content_type_id)
                ->max('sort_order')) + 1;
            $clone->save();

            $clone->translations()->create([
                'locale' => $targetLocale,
                'title' => $source->title,
                'slug' => $this->uniqueSlug($source->slug, $targetLocale, 'content'),
                'summary' => $source->summary,
                'body' => $source->body,
                'seo_title' => $source->seo_title,
                'seo_description' => $source->seo_description,
                'custom_fields' => $this->duplicateDynamicFiles($source->custom_fields ?? [], (int) $item->site_id, 'content'),
                'view_count' => 0,
            ]);

            if (!empty($config['category_relation'])) {
                $targetTermIds = $this->localizedTermIds($item->terms()->get()->pluck('id')->all(), $targetLocale);
                if ($targetTermIds !== []) {
                    $clone->terms()->sync(
                        collect($targetTermIds)
                            ->values()
                            ->mapWithKeys(fn ($termId, $index) => [(int) $termId => ['sort_order' => $index + 1]])
                            ->all()
                    );
                }
            }

            $this->duplicateMediaRelations($item, $clone, $config);

            return;
        }

        if ($item instanceof Product) {
            $source = $item->translation($sourceLocale);
            if (!$source) {
                return;
            }

            $clone = $item->replicate();
            $clone->view_count = 0;
            $clone->sort_order = ((int) Product::query()
                ->where('site_id', $item->site_id)
                ->max('sort_order')) + 1;
            $clone->save();

            $clone->translations()->create([
                'locale' => $targetLocale,
                'name' => $source->name,
                'slug' => $this->uniqueSlug($source->slug, $targetLocale, 'product'),
                'summary' => $source->summary,
                'description' => $source->description,
                'seo_title' => $source->seo_title,
                'seo_description' => $source->seo_description,
                'view_count' => 0,
            ]);

            if (!empty($config['category_relation'])) {
                $targetTermIds = $this->localizedTermIds($item->categories()->get()->pluck('id')->all(), $targetLocale);
                if ($targetTermIds !== []) {
                    $clone->categories()->sync(
                        collect($targetTermIds)
                            ->values()
                            ->mapWithKeys(fn ($termId, $index) => [(int) $termId => ['sort_order' => $index + 1]])
                            ->all()
                    );
                }
            }

            $this->duplicateMediaRelations($item, $clone, $config);
        }
    }

    /**
     * @param array<int> $sourceTermIds
     * @return array<int>
     */
    private function localizedTermIds(array $sourceTermIds, string $targetLocale): array
    {
        return collect($sourceTermIds)
            ->map(fn ($termId) => $this->localizedTermId((int) $termId, $targetLocale))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function localizedTermId(int $sourceTermId, string $targetLocale): ?int
    {
        $source = TaxonomyTerm::query()->with('parent')->find($sourceTermId);
        if (!$source) {
            return null;
        }

        if ($source->locale === $targetLocale) {
            return $source->id;
        }

        $targetParentId = $source->parent_id
            ? $this->localizedTermId((int) $source->parent_id, $targetLocale)
            : null;

        $existing = TaxonomyTerm::query()
            ->where('taxonomy_id', $source->taxonomy_id)
            ->where('locale', $targetLocale)
            ->where('slug', $source->slug)
            ->where(function (Builder $query) use ($targetParentId): void {
                $targetParentId
                    ? $query->where('parent_id', $targetParentId)
                    : $query->whereNull('parent_id');
            })
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $clone = $source->replicate();
        $clone->parent_id = $targetParentId;
        $clone->locale = $targetLocale;
        $clone->sort_order = ((int) TaxonomyTerm::query()
            ->where('taxonomy_id', $source->taxonomy_id)
            ->where('locale', $targetLocale)
            ->where(function (Builder $query) use ($targetParentId): void {
                $targetParentId
                    ? $query->where('parent_id', $targetParentId)
                    : $query->whereNull('parent_id');
            })
            ->max('sort_order')) + 1;
        $clone->save();

        return $clone->id;
    }

    private function duplicateMediaRelations(Model $source, Model $clone, array $config): void
    {
        if (!in_array($config['strategy'] ?? null, ['content', 'product'], true)) {
            return;
        }

        $pivotTable = $config['strategy'] === 'content' ? 'content_media' : 'product_media';
        $foreignKey = $config['strategy'] === 'content' ? 'content_id' : 'product_id';

        DB::table($pivotTable)
            ->where($foreignKey, $source->getKey())
            ->orderBy('sort_order')
            ->get()
            ->each(function ($pivot) use ($pivotTable, $foreignKey, $clone): void {
                $newMediaId = $this->duplicateMediaFile((int) $pivot->media_file_id);
                if (!$newMediaId) {
                    return;
                }

                DB::table($pivotTable)->insert([
                    $foreignKey => $clone->getKey(),
                    'media_file_id' => $newMediaId,
                    'role' => $pivot->role,
                    'sort_order' => $pivot->sort_order,
                    ...($pivotTable === 'content_media' ? ['metadata' => $pivot->metadata ?? null] : []),
                ]);
            });
    }

    private function duplicateMediaFile(int $mediaId): ?int
    {
        $media = DB::table('media_files')->where('id', $mediaId)->first();
        if (!$media) {
            return null;
        }

        $disk = $media->disk ?: 'public';
        if (!$media->path || !Storage::disk($disk)->exists((string) $media->path)) {
            return null;
        }

        $newPath = $this->copyStoredFile($disk, (string) $media->path);

        return (int) DB::table('media_files')->insertGetId([
            'site_id' => $media->site_id,
            'disk' => $disk,
            'path' => $newPath,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes,
            'width' => $media->width,
            'height' => $media->height,
            'alt_text' => $media->alt_text,
            'metadata' => $media->metadata,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function duplicateDynamicFiles(array $customFields, int $siteId, string $strategy): array
    {
        $duplicated = $customFields;

        $walker = function (&$value) use (&$walker, $siteId, $strategy): void {
            if (!is_array($value)) {
                return;
            }

            if (!empty($value['path']) && is_string($value['path'])) {
                $disk = (string) ($value['disk'] ?? 'public');
                if (!Storage::disk($disk)->exists($value['path'])) {
                    return;
                }

                $value['path'] = $this->copyStoredFile($disk, $value['path'], "sites/{$siteId}/{$strategy}/dynamic");
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

    private function statusColor(string $status): string
    {
        return match ($status) {
            'hidden', 'archived', 'cancelled' => '#dc3545',
            'draft', 'pending' => '#ffc107',
            'processing', 'scheduled' => '#17a2b8',
            default => '#28a745',
        };
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, mixed> $conditions
     */
    private function nextSortOrder(string $model, array $conditions): int
    {
        return ((int) $model::query()
            ->where($conditions)
            ->max('sort_order')) + 1;
    }

    private function reorderItems(Builder $scope, Model $movingItem, int $targetPosition): void
    {
        $this->normalizeSortOrder($scope);

        $ids = (clone $scope)
            ->whereKeyNot($movingItem->getKey())
            ->orderBy('sort_order')
            ->orderBy($movingItem->getKeyName())
            ->pluck($movingItem->getKeyName())
            ->all();

        $targetIndex = max(0, min($targetPosition - 1, count($ids)));
        array_splice($ids, $targetIndex, 0, [$movingItem->getKey()]);

        foreach (array_values($ids) as $index => $id) {
            (clone $scope)->whereKey($id)->update(['sort_order' => $index + 1]);
        }
    }

    private function withoutPinned(Builder $scope): Builder
    {
        if (!$this->hasPinColumn($scope)) {
            return $scope;
        }

        $table = $scope->getModel()->getTable();

        return $scope->where(function (Builder $query) use ($table): void {
            $query->where("{$table}.is_pinned", false)
                ->orWhereNull("{$table}.is_pinned");
        });
    }

    private function hasPinColumn(Builder $scope): bool
    {
        return Schema::hasColumn($scope->getModel()->getTable(), 'is_pinned');
    }

    private function normalizeSortOrder(Builder $scope): void
    {
        $model = $scope->getModel();
        $keyName = $model->getKeyName();

        $ids = (clone $scope)
            ->orderBy('sort_order')
            ->orderBy($keyName)
            ->pluck($keyName)
            ->all();

        foreach (array_values($ids) as $index => $id) {
            (clone $scope)->whereKey($id)->update(['sort_order' => $index + 1]);
        }
    }

    private function applyTermScope(Builder $query, array $config, int $termId): void
    {
        $filterTermIds = $this->termIdsIncludingDescendants($termId);
        $query->whereHas(
            $config['category_relation'],
            fn (Builder $termQuery) => $termQuery->whereIn('taxonomy_terms.id', $filterTermIds)
        );
    }

    private function applyResourceSortOrder(Builder $query, array $config, int $siteId, int $termId): void
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $keyName = $model->getKeyName();
        $hasPinColumn = $this->hasPinColumn($query);

        $query
            ->leftJoin('resource_sort_orders as resource_scope_sort', function ($join) use ($table, $keyName, $config, $siteId, $termId): void {
                $join->on('resource_scope_sort.resource_id', '=', "{$table}.{$keyName}")
                    ->where('resource_scope_sort.site_id', $siteId)
                    ->where('resource_scope_sort.resource', $this->resourceSortKey($config))
                    ->where('resource_scope_sort.taxonomy_term_id', $termId);
            })
            ->select("{$table}.*")
            ->selectRaw('resource_scope_sort.sort_order as cms_scope_sort_order')
            ->when($hasPinColumn, fn (Builder $q) => $q->orderByDesc("{$table}.is_pinned"))
            ->orderBy('resource_scope_sort.sort_order')
            ->orderBy("{$table}.{$keyName}");
    }

    private function normalizeResourceSortScope(array $config, Builder $scope, int $siteId, int $termId): void
    {
        $model = $scope->getModel();
        $keyName = $model->getKeyName();
        $scope = $this->withoutPinned($scope);

        $ids = (clone $scope)
            ->orderBy('sort_order')
            ->orderBy($keyName)
            ->pluck($keyName)
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $resource = $this->resourceSortKey($config);
        $existing = DB::table('resource_sort_orders')
            ->where('site_id', $siteId)
            ->where('resource', $resource)
            ->where('taxonomy_term_id', $termId)
            ->whereIn('resource_id', $ids)
            ->orderBy('sort_order')
            ->orderBy('resource_id')
            ->pluck('resource_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $orderedIds = array_values(array_unique(array_merge(
            array_values(array_intersect($existing, $ids)),
            array_values(array_diff($ids, $existing))
        )));

        $now = now();
        foreach ($orderedIds as $index => $id) {
            DB::table('resource_sort_orders')->updateOrInsert(
                [
                    'site_id' => $siteId,
                    'resource' => $resource,
                    'taxonomy_term_id' => $termId,
                    'resource_id' => $id,
                ],
                [
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function reorderResourceSortScope(array $config, Builder $scope, Model $movingItem, int $siteId, int $termId, int $targetPosition): void
    {
        $scope = $this->withoutPinned($scope);
        $this->normalizeResourceSortScope($config, $scope, $siteId, $termId);
        $scopeIds = (clone $scope)
            ->pluck($movingItem->getKeyName())
            ->map(fn ($id) => (int) $id)
            ->all();

        $ids = DB::table('resource_sort_orders')
            ->where('site_id', $siteId)
            ->where('resource', $this->resourceSortKey($config))
            ->where('taxonomy_term_id', $termId)
            ->whereIn('resource_id', $scopeIds)
            ->orderBy('sort_order')
            ->orderBy('resource_id')
            ->pluck('resource_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(in_array((int) $movingItem->getKey(), $ids, true), 422);

        $ids = array_values(array_filter($ids, fn (int $id) => $id !== (int) $movingItem->getKey()));
        $targetIndex = max(0, min($targetPosition - 1, count($ids)));
        array_splice($ids, $targetIndex, 0, [(int) $movingItem->getKey()]);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        $now = now();
        foreach ($ids as $index => $id) {
            DB::table('resource_sort_orders')->updateOrInsert(
                [
                    'site_id' => $siteId,
                    'resource' => $this->resourceSortKey($config),
                    'taxonomy_term_id' => $termId,
                    'resource_id' => $id,
                ],
                [
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function normalizeExistingResourceSortScopes(array $config, int $siteId, string $locale): void
    {
        $termIds = DB::table('resource_sort_orders')
            ->where('site_id', $siteId)
            ->where('resource', $this->resourceSortKey($config))
            ->whereNotNull('taxonomy_term_id')
            ->distinct()
            ->pluck('taxonomy_term_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($termIds as $termId) {
            $scope = $this->baseQuery($config, $siteId, $locale);
            $this->applyTermScope($scope, $config, $termId);
            $this->normalizeResourceSortScope($config, $scope, $siteId, $termId);
        }
    }

    private function resourceSortKey(array $config): string
    {
        return (string) ($config['adminKey'] ?? $config['module'] ?? $config['content_type'] ?? $config['strategy']);
    }

    private function taxonomyOptions(array $config, $site, ?string $locale): array
    {
        $fields = $this->taxonomyFields($config);
        if (!$site || $fields === []) {
            return [];
        }

        $field = $fields[0];
        $taxonomyCode = $this->fieldTaxonomyCode($field, $config);

        $taxonomy = $this->treeService->taxonomyForSite(
            $site,
            $taxonomyCode,
            $this->taxonomyLabel($taxonomyCode, $config),
            (bool) ($field['taxonomy_hierarchical'] ?? $config['taxonomy_hierarchical'] ?? true)
        );

        return $this->treeService->flattenedOptions($taxonomy, $locale)->all();
    }

    private function taxonomyOptionsByField(array $config, $site, ?string $locale): array
    {
        if (!$site) {
            return [];
        }

        $options = [];
        foreach ($this->taxonomyFields($config) as $field) {
            $taxonomyCode = $this->fieldTaxonomyCode($field, $config);
            if ($taxonomyCode === '') {
                continue;
            }

            $taxonomy = $this->treeService->taxonomyForSite(
                $site,
                $taxonomyCode,
                $this->taxonomyLabel($taxonomyCode, $config),
                (bool) ($field['taxonomy_hierarchical'] ?? $config['taxonomy_hierarchical'] ?? true)
            );

            $options[$field['name']] = $this->treeService->flattenedOptions($taxonomy, $locale)->all();
        }

        return $options;
    }

    private function taxonomyTreesByField(array $config, $site, ?string $locale): array
    {
        if (!$site) {
            return [];
        }

        $trees = [];
        foreach ($this->taxonomyFields($config) as $field) {
            if (($field['type'] ?? null) !== 'linked_taxonomy') {
                continue;
            }

            $taxonomyCode = $this->fieldTaxonomyCode($field, $config);
            if ($taxonomyCode === '') {
                continue;
            }

            $taxonomy = $this->treeService->taxonomyForSite(
                $site,
                $taxonomyCode,
                $this->taxonomyLabel($taxonomyCode, $config),
                true
            );

            $terms = TaxonomyTerm::query()
                ->where('taxonomy_id', $taxonomy->id)
                ->where('locale', $locale ?? $site->default_locale)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'parent_id', 'name']);

            $trees[$field['name']] = $terms
                ->map(fn (TaxonomyTerm $term) => [
                    'id' => $term->id,
                    'parent_id' => $term->parent_id,
                    'label' => $term->name,
                ])
                ->values()
                ->all();
        }

        return $trees;
    }

    /**
     * @return array<int>
     */
    private function termIdsIncludingDescendants(int $termId): array
    {
        $term = TaxonomyTerm::query()->find($termId);
        if (!$term) {
            return [$termId];
        }

        $terms = TaxonomyTerm::query()
            ->where('taxonomy_id', $term->taxonomy_id)
            ->where('locale', $term->locale)
            ->get(['id', 'parent_id']);

        $ids = [$termId];
        $pending = [$termId];

        while ($pending) {
            $parentId = array_shift($pending);
            $children = $terms->where('parent_id', $parentId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($children as $childId) {
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $pending[] = $childId;
                }
            }
        }

        return $ids;
    }

    private function selectedTermIds(?Model $item, array $config): array
    {
        if (!$item || empty($config['category_relation'])) {
            return [];
        }

        return $item->{$config['category_relation']}->pluck('id')->all();
    }

    private function selectedTermIdsByField(?Model $item, array $config): array
    {
        $selected = [];
        foreach ($this->taxonomyFields($config) as $field) {
            $selected[$field['name']] = [];
        }

        if (!$item || empty($config['category_relation'])) {
            return $selected;
        }

        $terms = $item->{$config['category_relation']}()->with('taxonomy')->get();
        foreach ($this->taxonomyFields($config) as $field) {
            $taxonomyCode = $this->fieldTaxonomyCode($field, $config);
            $selected[$field['name']] = $terms
                ->filter(fn (TaxonomyTerm $term) => $term->taxonomy?->code === $taxonomyCode)
                ->pluck('id')
                ->all();
        }

        return $selected;
    }

    private function defaultSelectedTermIdsByField(array $config, array $taxonomyOptionsByField, ?int $requestedTermId): array
    {
        $selected = [];
        $fields = $this->taxonomyFields($config);

        foreach ($fields as $field) {
            $selected[$field['name']] = [];
        }

        $primaryField = $fields[0] ?? null;
        if (!$primaryField) {
            return $selected;
        }

        $fieldName = $primaryField['name'];
        $options = collect($taxonomyOptionsByField[$fieldName] ?? [])
            ->map(fn (array $option) => (int) $option['id'])
            ->filter()
            ->values();

        if ($options->isEmpty()) {
            return $selected;
        }

        if (($primaryField['type'] ?? null) === 'linked_taxonomy' && !$requestedTermId) {
            return $selected;
        }

        $termId = $requestedTermId && $options->contains($requestedTermId)
            ? $requestedTermId
            : (int) $options->first();

        $selected[$fieldName] = [$termId];

        return $selected;
    }

    private function taxonomyFields(array $config): array
    {
        return collect($config['form_sections'] ?? [])
            ->flatMap(fn (array $section) => $section['fields'] ?? [])
            ->filter(fn (array $field) => in_array($field['type'] ?? null, ['taxonomy', 'linked_taxonomy'], true))
            ->values()
            ->all();
    }

    private function fieldTaxonomyCode(array $field, array $config): string
    {
        $category = $field['taxonomy_code'] ?? $field['category'] ?? $config['taxonomy'] ?? '';

        if (is_array($category)) {
            return (string) end($category);
        }

        return (string) $category;
    }

    private function taxonomyLabel(string $taxonomyCode, array $config): string
    {
        if (($config['taxonomy'] ?? null) === $taxonomyCode && !empty($config['taxonomy_label'])) {
            return (string) $config['taxonomy_label'];
        }

        return CmsSetLoader::get($taxonomyCode, 'taxonomy')['taxonomy_label']
            ?? CmsSetLoader::get($taxonomyCode, 'taxonomy')['label']
            ?? $taxonomyCode;
    }

    private function mediaByRoleForItem(?Model $item, array $config): array
    {
        if (!$item || !in_array($config['strategy'], ['content', 'product'], true)) {
            return [];
        }

        $pivotTable = $config['strategy'] === 'content' ? 'content_media' : 'product_media';
        $foreignKey = $config['strategy'] === 'content' ? 'content_id' : 'product_id';

        return DB::table($pivotTable)
            ->join('media_files', 'media_files.id', '=', "{$pivotTable}.media_file_id")
            ->where("{$pivotTable}.{$foreignKey}", $item->getKey())
            ->orderBy("{$pivotTable}.sort_order")
            ->select([
                'media_files.id',
                'media_files.path',
                'media_files.alt_text',
                "{$pivotTable}.role",
                "{$pivotTable}.sort_order",
            ])
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

    private function syncMediaUploads(Request $request, Model $item, array $config, int $siteId): void
    {
        if (!in_array($config['strategy'], ['content', 'product'], true)) {
            return;
        }

        foreach ($request->input('delete_file', []) as $mediaId) {
            $this->detachMedia($item, $config, (int) $mediaId);
        }

        foreach ($request->input('update_file_title', []) as $mediaId => $title) {
            DB::table('media_files')->where('id', (int) $mediaId)->update([
                'alt_text' => $title,
                'updated_at' => now(),
            ]);
        }

        foreach ($this->imageUploadFields($config) as $field) {
            $fieldName = $field['name'];
            $role = $field['file_type'] ?? $fieldName;
            $multiple = (bool) ($field['multiple'] ?? false);

            foreach ((array) $request->file("{$fieldName}_update", []) as $mediaId => $file) {
                if ($file) {
                    $this->replaceMediaFile((int) $mediaId, $file, $siteId, $config['strategy'], $role, (string) $request->input("update_file_title.{$mediaId}", ''));
                }
            }

            $files = $request->file($fieldName, []);
            if ($files instanceof \Illuminate\Http\UploadedFile) {
                $files = [$files];
            }

            if (!$multiple && !empty($files)) {
                $this->detachMediaByRole($item, $config, $role);
            }

            foreach ((array) $files as $index => $file) {
                if (!$file) {
                    continue;
                }

                $mediaId = $this->storeMediaFile($file, $siteId, $config['strategy'], $role, (string) $request->input("{$fieldName}_title.{$index}", ''));
                $this->attachMedia($item, $config, $mediaId, $role);
            }
        }
    }

    private function imageUploadFields(array $config): array
    {
        return collect($config['form_sections'] ?? [])
            ->flatMap(fn ($section) => $section['fields'] ?? [])
            ->filter(fn ($field) => ($field['type'] ?? null) === 'image_upload')
            ->values()
            ->all();
    }

    private function dropzoneImageField(array $config, string $fieldName, string $fileType): ?array
    {
        return collect($this->imageUploadFields($config))
            ->first(function (array $field) use ($fieldName, $fileType): bool {
                if (empty($field['dropzone'])) {
                    return false;
                }

                $name = (string) ($field['name'] ?? '');
                $role = (string) ($field['file_type'] ?? $name);

                return ($fieldName !== '' && $fieldName === $name)
                    || ($fileType !== '' && $fileType === $role);
            });
    }

    private function syncDynamicFields(Request $request, Model $item, array $config, int $siteId, string $locale): void
    {
        if (!$item instanceof Content || empty($this->dynamicFieldConfigs($config))) {
            return;
        }

        $translation = $item->translations()->where('locale', $locale)->first();
        if (!$translation) {
            return;
        }

        $customFields = $translation->custom_fields ?? [];
        $oldPaths = $this->dynamicFilePaths($customFields);

        foreach ($this->dynamicFieldConfigs($config) as $field) {
            $customFields[$field['name']] = $this->normalizeDynamicRows(
                $request,
                $field,
                $siteId,
                $config['strategy'],
                $customFields[$field['name']] ?? []
            );
        }

        $newPaths = $this->dynamicFilePaths($customFields);
        foreach (array_diff($oldPaths, $newPaths) as $path) {
            Storage::disk('public')->delete($path);
        }

        $translation->forceFill(['custom_fields' => $customFields])->save();
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int, array<string, mixed>>  $oldRows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDynamicRows(Request $request, array $field, int $siteId, string $strategy, array $oldRows): array
    {
        $name = $field['name'];
        $inputRows = $request->input($name, []);
        $fileRows = $request->file($name, []);
        $rowIndexes = collect(array_keys((array) $inputRows))
            ->merge(array_keys((array) $fileRows))
            ->unique()
            ->sortBy(fn ($index) => (int) $index)
            ->values();

        $rows = [];

        foreach ($rowIndexes as $rowIndex) {
            $row = [];
            $uid = data_get($inputRows, "{$rowIndex}._uid");
            if (is_string($uid) && trim($uid) !== '') {
                $row['_uid'] = trim($uid);
            }

            foreach ($field['fields'] ?? [] as $subField) {
                $subName = $subField['name'];
                $subType = $subField['type'] ?? 'text';

                if (in_array($subType, ['image', 'image_upload', 'file', 'file_upload'], true)) {
                    $uploadedFile = data_get($fileRows, "{$rowIndex}.{$subName}");
                    $existing = $this->decodeExistingDynamicFile(data_get($inputRows, "{$rowIndex}.{$subName}._existing"));
                    $title = data_get($inputRows, "{$rowIndex}.{$subName}._title");
                    $title = is_string($title) ? trim($title) : null;

                    if ($uploadedFile instanceof UploadedFile) {
                        $row[$subName] = array_filter(
                            [
                                ...$this->storeDynamicFile($uploadedFile, $siteId, $strategy, $field, $subField),
                                'title' => $title,
                            ],
                            fn ($value) => $value !== null && $value !== ''
                        );
                    } elseif ($existing) {
                        $existing['title'] = $title ?? ($existing['title'] ?? null);
                        $row[$subName] = $existing;
                    }

                    continue;
                }

                $value = data_get($inputRows, "{$rowIndex}.{$subName}");
                if (is_string($value)) {
                    $value = trim($value);
                }

                if ($value !== null && $value !== '') {
                    $row[$subName] = $value;
                }
            }

            if ($this->dynamicRowHasValue($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function storeDynamicFile(UploadedFile $file, int $siteId, string $strategy, array $dynamicField, array $subField): array
    {
        $subType = $subField['type'] ?? 'file';
        $fileType = $subField['fileType'] ?? $subField['file_type'] ?? $subField['name'];
        $maxSizeMb = $subField['maxSize'] ?? data_get($subField, 'size.maxSize');

        if ($maxSizeMb && $file->getSize() > ((int) $maxSizeMb * 1024 * 1024)) {
            throw ValidationException::withMessages([
                $dynamicField['name'] => "{$subField['label']} 檔案大小不可超過 {$maxSizeMb}MB",
            ]);
        }

        if (in_array($subType, ['image', 'image_upload'], true) && !str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw ValidationException::withMessages([
                $dynamicField['name'] => "{$subField['label']} 必須是圖片檔",
            ]);
        }

        $format = $subField['format'] ?? null;
        if ($format) {
            $allowed = collect(explode(',', $format))
                ->map(fn ($extension) => strtolower(ltrim(trim($extension), '.')))
                ->filter()
                ->values()
                ->all();

            if ($allowed && !in_array(strtolower($file->getClientOriginalExtension()), $allowed, true)) {
                throw ValidationException::withMessages([
                    $dynamicField['name'] => "{$subField['label']} 檔案格式必須是 {$format}",
                ]);
            }
        }

        $path = $file->store("sites/{$siteId}/{$strategy}/dynamic/{$fileType}", 'public');
        [$width, $height] = in_array($subType, ['image', 'image_upload'], true)
            ? $this->imageDimensions(Storage::disk('public')->path($path))
            : [null, null];

        return [
            'disk' => 'public',
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'file_type' => $fileType,
        ];
    }

    private function decodeExistingDynamicFile(mixed $encoded): ?array
    {
        if (!is_string($encoded) || $encoded === '') {
            return null;
        }

        $decoded = json_decode(base64_decode($encoded, true) ?: '', true);

        return is_array($decoded) ? $decoded : null;
    }

    private function dynamicRowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if (is_array($value) ? !empty($value) : ($value !== null && $value !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dynamicFieldConfigs(array $config): array
    {
        return collect($config['form_sections'] ?? [])
            ->flatMap(fn ($section) => $section['fields'] ?? [])
            ->filter(fn ($field) => ($field['type'] ?? null) === 'dynamic_fields')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function dynamicFilePaths(array $customFields): array
    {
        $paths = [];
        array_walk_recursive($customFields, function ($value, $key) use (&$paths): void {
            if ($key === 'path' && is_string($value) && $value !== '') {
                $paths[] = $value;
            }
        });

        return array_values(array_unique($paths));
    }

    private function storeMediaFile(\Illuminate\Http\UploadedFile $file, int $siteId, string $strategy, string $role, string $title): int
    {
        $path = $file->store("sites/{$siteId}/{$strategy}/{$role}", 'public');
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

    private function replaceMediaFile(int $mediaId, \Illuminate\Http\UploadedFile $file, int $siteId, string $strategy, string $role, string $title): void
    {
        $existing = DB::table('media_files')->where('id', $mediaId)->first();
        if (!$existing) {
            return;
        }

        $path = $file->store("sites/{$siteId}/{$strategy}/{$role}", 'public');
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

    private function attachMedia(Model $item, array $config, int $mediaId, string $role): void
    {
        $pivotTable = $config['strategy'] === 'content' ? 'content_media' : 'product_media';
        $foreignKey = $config['strategy'] === 'content' ? 'content_id' : 'product_id';
        $sortOrder = ((int) DB::table($pivotTable)->where($foreignKey, $item->getKey())->where('role', $role)->max('sort_order')) + 1;

        DB::table($pivotTable)->insert([
            $foreignKey => $item->getKey(),
            'media_file_id' => $mediaId,
            'role' => $role,
            'sort_order' => $sortOrder,
            ...($pivotTable === 'content_media' ? ['metadata' => null] : []),
        ]);
    }

    private function detachMedia(Model $item, array $config, int $mediaId): void
    {
        $pivotTable = $config['strategy'] === 'content' ? 'content_media' : 'product_media';
        $foreignKey = $config['strategy'] === 'content' ? 'content_id' : 'product_id';

        DB::table($pivotTable)->where($foreignKey, $item->getKey())->where('media_file_id', $mediaId)->delete();
    }

    private function detachMediaByRole(Model $item, array $config, string $role): void
    {
        $pivotTable = $config['strategy'] === 'content' ? 'content_media' : 'product_media';
        $foreignKey = $config['strategy'] === 'content' ? 'content_id' : 'product_id';

        DB::table($pivotTable)->where($foreignKey, $item->getKey())->where('role', $role)->delete();
    }

    private function imageDimensions(string $path): array
    {
        $size = @getimagesize($path);

        return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
    }

    private function valuesForItem(Model $item, array $config, string $locale): array
    {
        if ($item instanceof Content) {
            $translation = $item->translation($locale);

            return [
                'locale' => $translation?->locale ?? $locale,
                'title' => $translation?->title,
                'slug' => $translation?->slug,
                'summary' => $translation?->summary,
                'body' => $translation?->body,
                'seo_title' => $translation?->seo_title,
                'seo_description' => $translation?->seo_description,
                'status' => $item->status,
                'is_pinned' => $item->is_pinned,
                'sort_order' => $item->sort_order,
                'published_at' => $item->published_at,
                ...collect($this->dynamicFieldConfigs($config))
                    ->mapWithKeys(fn ($field) => [$field['name'] => data_get($translation?->custom_fields ?? [], $field['name'], [])])
                    ->all(),
            ];
        }

        if ($item instanceof Product) {
            $translation = $item->translation($locale);

            return [
                'locale' => $translation?->locale ?? $locale,
                'name' => $translation?->name,
                'slug' => $translation?->slug,
                'summary' => $translation?->summary,
                'description' => $translation?->description,
                'seo_title' => $translation?->seo_title,
                'seo_description' => $translation?->seo_description,
                'sku' => $item->sku,
                'base_price' => $item->base_price,
                'compare_at_price' => $item->compare_at_price,
                'cost_price' => $item->cost_price,
                'status' => $item->status,
                'is_taxable' => $item->is_taxable,
                'sort_order' => $item->sort_order,
                'published_at' => $item->published_at,
                'updated_at' => $item->updated_at,
            ];
        }

        if ($item instanceof ContactMessage) {
            return [
                ...$item->attributesToArray(),
                'inquiry' => $item->type,
                'preferred_contact_time' => null,
                'contact_date' => null,
            ];
        }

        if ($item instanceof LanguagePack) {
            return [
                ...$item->attributesToArray(),
                'translations' => $item->translations->pluck('value', 'locale')->all(),
            ];
        }

        return $item->attributesToArray();
    }

    private function emptyValues(array $config, string $locale): array
    {
        if (($config['strategy'] ?? null) === 'language') {
            return [
                'locale' => $locale,
                'is_active' => true,
                'is_default' => false,
            ];
        }

        if (($config['strategy'] ?? null) === 'language_pack') {
            return [
                'locale' => $locale,
                'translations' => [],
            ];
        }

        $values = ['locale' => $locale, 'status' => array_key_first($config['status_options'] ?? [])];

        foreach ($config['form_sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $values[$field['name']] = match ($field['type']) {
                    'checkbox' => false,
                    'dynamic_fields' => [],
                    default => null,
                };
            }
        }

        return $values;
    }

    private function uniqueSlug(string $source, string $locale, string $type, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $candidate = $base;
        $counter = 2;
        $model = $type === 'product' ? \App\Models\ProductTranslation::class : \App\Models\ContentTranslation::class;

        while (
            $model::query()
                ->withTrashed()
                ->where('locale', $locale)
                ->where('slug', $candidate)
                ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function viewData(AdminContext $context, string $resource, array $config, array $data): array
    {
        return array_merge([
            'admin' => $context->user(),
            'site' => $context->site(),
            'sites' => $context->sites(),
            'resource' => $resource,
            'resourceConfig' => $config,
        ], $data);
    }

    private function languageRouteParams(array $languageContext, bool $enabled): array
    {
        return $enabled ? ['language' => $languageContext['slug']] : [];
    }

    private function localeForConfig(array $config, $site, ?Request $request = null): string
    {
        if (AdminLanguage::enabledFor($config)) {
            return AdminLanguage::context($site, $request ?? request())['locale'];
        }

        return $site?->default_locale ?? app()->getLocale();
    }
}

