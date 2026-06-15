<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Product;
use App\Models\TaxonomyTerm;
use App\Services\TaxonomyTreeService;
use App\Support\AdminContext;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $locale = $site?->default_locale ?? app()->getLocale();
        $keyword = trim((string) $request->query('search', ''));
        $termId = $request->integer('term_id') ?: null;
        $trash = $request->boolean('trash');

        $query = $this->baseQuery($config, $site?->id, $locale);
        if ($trash) {
            $query->onlyTrashed();
        }

        if ($keyword !== '') {
            $this->applySearch($query, $config, $keyword);
        }

        if ($termId && !empty($config['category_relation'])) {
            $filterTermIds = $this->termIdsIncludingDescendants($termId);
            $query->whereHas($config['category_relation'], fn (Builder $termQuery) => $termQuery->whereIn('taxonomy_terms.id', $filterTermIds));
        }

        if (!$trash && ($config['sort_column'] ?? null) === 'sort_order') {
            $this->normalizeSortOrder($this->baseQuery($config, $site?->id, $locale));
        }

        $items = $query
            ->when(($config['sort_column'] ?? null) === 'sort_order', fn (Builder $q) => $q->orderBy('sort_order'))
            ->latest('updated_at')
            ->paginate((int) $request->query('per_page', 12))
            ->withQueryString();

        $sortOptionCount = $this->baseQuery($config, $site?->id, $locale)->count();

        return view('admin.resources.index', $this->viewData($context, $resource, $config, [
            'items' => $items,
            'keyword' => $keyword,
            'termId' => $termId,
            'trash' => $trash,
            'trashCount' => (clone $this->baseQuery($config, $site?->id, $locale))->onlyTrashed()->count(),
            'sortOptionCount' => $sortOptionCount,
            'taxonomyOptions' => $this->taxonomyOptions($config, $site, $locale),
            'taxonomyTreesByField' => $this->taxonomyTreesByField($config, $site, $locale),
        ]));
    }

    public function create(AdminContext $context, Request $request, string $resource): View
    {
        $config = $this->config($resource);
        abort_if(($config['show_add_button'] ?? true) === false, 404);
        $site = $context->site();
        $locale = $site?->default_locale ?? app()->getLocale();
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
        ]));
    }

    public function store(AdminContext $context, Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        abort_if(($config['show_add_button'] ?? true) === false, 404);
        $site = $context->site();
        abort_unless($site, 404);

        $data = $this->validatedData($request, $config, $site->default_locale);

        DB::transaction(function () use ($request, $config, $site, $data): void {
            $item = match ($config['strategy']) {
                'content' => $this->storeContent($config, $site->id, $data),
                'product' => $this->storeProduct($site->id, $data),
                default => throw new \InvalidArgumentException('不支援的資源策略。'),
            };

            $this->syncTerms($item, $config, $data);
            $this->syncMediaUploads($request, $item, $config, $site->id);
            $this->syncDynamicFields($request, $item, $config, $site->id, $data['locale']);
        });

        return redirect()->route("admin.{$resource}.index")->with('status', "{$config['label']}已新增。");
    }

    public function edit(AdminContext $context, int $id, string $resource): View
    {
        $config = $this->config($resource);
        $site = $context->site();
        $locale = $site?->default_locale ?? app()->getLocale();
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
        ]));
    }

    public function update(AdminContext $context, Request $request, int $id, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $locale = $site?->default_locale ?? app()->getLocale();
        $item = $this->findItem($config, $id, $site?->id, $locale);
        $data = $this->validatedData($request, $config, $locale, $item);

        DB::transaction(function () use ($request, $item, $config, $site, $data, $locale): void {
            match ($config['strategy']) {
                'content' => $this->updateContent($item, $data, $locale),
                'product' => $this->updateProduct($item, $data, $locale),
                'contact' => $this->updateContact($item, $data),
                default => throw new \InvalidArgumentException('不支援的資源策略。'),
            };

            $this->syncTerms($item, $config, $data);
            $this->syncMediaUploads($request, $item, $config, (int) $site?->id);
            $this->syncDynamicFields($request, $item, $config, (int) $site?->id, $data['locale']);
        });

        return redirect()->route("admin.{$resource}.edit", $item)->with('status', "{$config['label']}已更新。");
    }

    public function destroy(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $site?->default_locale ?? app()->getLocale());

        DB::transaction(fn () => $item->delete());

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已移至垃圾桶。",
                'redirect_url' => route("admin.{$resource}.index"),
            ]);
        }

        return redirect()->route("admin.{$resource}.index")->with('status', "{$config['label']}已移至垃圾桶。");
    }

    public function restore(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $locale = $site?->default_locale ?? app()->getLocale();
        $item = $this->baseQuery($config, $site?->id, $locale)->onlyTrashed()->whereKey($id)->firstOrFail();

        DB::transaction(fn () => $item->restore());

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已還原。",
                'redirect_url' => route("admin.{$resource}.index"),
            ]);
        }

        return redirect()->route("admin.{$resource}.index")->with('status', "{$config['label']}已還原。");
    }

    public function forceDelete(AdminContext $context, int $id, string $resource): RedirectResponse|JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $locale = $site?->default_locale ?? app()->getLocale();
        $item = $this->baseQuery($config, $site?->id, $locale)->onlyTrashed()->whereKey($id)->firstOrFail();

        DB::transaction(function () use ($item, $config): void {
            $this->detachItemRelationsBeforeForceDelete($item, $config);
            $item->forceDelete();
        });

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "{$config['label']}已永久刪除。",
                'redirect_url' => route("admin.{$resource}.index", ['trash' => 1]),
            ]);
        }

        return redirect()->route("admin.{$resource}.index", ['trash' => 1])->with('status', "{$config['label']}已永久刪除。");
    }

    public function toggleStatus(AdminContext $context, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $site?->default_locale ?? app()->getLocale());
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

    public function togglePin(AdminContext $context, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $site?->default_locale ?? app()->getLocale());
        abort_unless(array_key_exists('is_pinned', $item->getAttributes()), 404);

        $item->forceFill(['is_pinned' => !$item->is_pinned])->save();

        return response()->json(['is_pinned' => (bool) $item->is_pinned]);
    }

    public function sort(AdminContext $context, Request $request, int $id, string $resource): \Illuminate\Http\JsonResponse
    {
        $config = $this->config($resource);
        $site = $context->site();
        $item = $this->findItem($config, $id, $site?->id, $site?->default_locale ?? app()->getLocale());
        $column = $config['sort_column'] ?? 'sort_order';
        abort_unless($column === 'sort_order' && array_key_exists('sort_order', $item->getAttributes()), 404);

        $data = $request->validate(['sort_order' => ['required', 'integer', 'min:1']]);

        DB::transaction(function () use ($config, $site, $item, $data): void {
            $this->reorderItems(
                $this->baseQuery($config, $site?->id, $site?->default_locale ?? app()->getLocale()),
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

        $data = $request->validate([
            'action' => ['required', 'string', Rule::in(['delete', 'restore', 'force_delete', 'clone', 'clone_local'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $itemsQuery = $this->baseQuery($config, $site->id, $site->default_locale);
        if (in_array($data['action'], ['restore', 'force_delete'], true)) {
            $itemsQuery->onlyTrashed();
        }

        $items = $itemsQuery->whereKey($data['ids'])->get();

        DB::transaction(function () use ($items, $config, $site, $data): void {
            if ($data['action'] === 'delete') {
                $items->each->delete();
                return;
            }

            if ($data['action'] === 'restore') {
                $items->each->restore();
                return;
            }

            if ($data['action'] === 'force_delete') {
                $items->each(function (Model $item) use ($config): void {
                    $this->detachItemRelationsBeforeForceDelete($item, $config);
                    $item->forceDelete();
                });
                return;
            }


            abort_if($config['strategy'] === 'contact', 422, '聯絡訊息不能複製。');

            foreach ($items as $item) {
                $this->cloneItem($item, $config, $site->default_locale);
            }
        });

        return response()->json([
            'message' => $this->bulkActionMessage($data['action'], $items->count(), $config['label']),
            'redirect_url' => $data['action'] === 'restore' ? route("admin.{$resource}.index") : null,
        ]);
    }

    private function bulkActionMessage(string $action, int $count, string $label): string
    {
        return match ($action) {
            'delete' => "已移至垃圾桶 {$count} 筆{$label}。",
            'restore' => "已還原 {$count} 筆{$label}。",
            'force_delete' => "已永久刪除 {$count} 筆{$label}。",
            'clone', 'clone_local' => "已複製 {$count} 筆{$label}。",
            default => "已處理 {$count} 筆{$label}。",
        };
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

    private function baseQuery(array $config, ?int $siteId, string $locale): Builder
    {
        /** @var class-string<Model> $model */
        $model = $config['model'];

        return $model::query()
            ->where('site_id', $siteId)
            ->when($config['strategy'] === 'content', function (Builder $query) use ($config, $siteId): void {
                $typeId = ContentType::query()
                    ->where('site_id', $siteId)
                    ->where('code', $config['content_type'])
                    ->value('id');
                $query->where('content_type_id', $typeId)->with(['translations', 'terms.parent']);
            })
            ->when($config['strategy'] === 'product', fn (Builder $query) => $query->with(['translations', 'categories.parent']))
            ->when($config['strategy'] === 'contact', fn (Builder $query) => $query->where('type', 'contact'));
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
        $rules = [
            'locale' => ['nullable', 'string', 'max:20'],
        ];

        foreach ($this->taxonomyFields($config) as $field) {
            if (!empty($field['multiple'])) {
                $rules[$field['name']] = ['nullable', 'array'];
                $rules["{$field['name']}.*"] = ['integer', 'exists:taxonomy_terms,id'];
                continue;
            }

            $rules[$field['name']] = ['nullable', 'integer', 'exists:taxonomy_terms,id'];
        }

        foreach ($config['form_sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $name = $field['name'];
                if (
                    ($field['readonly'] ?? false)
                    || in_array($field['type'], ['taxonomy', 'linked_taxonomy', 'image_upload', 'file_upload', 'dynamic_fields', 'updatetime'], true)
                ) {
                    if (($field['type'] ?? null) === 'dynamic_fields') {
                        $rules[$name] = ['nullable', 'array'];
                    }

                    continue;
                }

                $rules[$name] = match ($field['type']) {
                    'number' => ['nullable', 'numeric'],
                    'checkbox' => ['nullable'],
                    'datetime' => ['nullable', 'date'],
                    'select' => ['nullable', 'string', Rule::in(array_keys($config['status_options'] ?? []))],
                    default => [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:5000'],
                };
            }
        }

        $data = $request->validate($rules);
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
            'status' => $data['status'] ?? $message->status,
            'is_read' => (bool) ($data['is_read'] ?? false),
            'admin_note' => $data['admin_note'] ?? null,
            'handled_at' => now(),
        ]);
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
                    'custom_fields' => $translation->custom_fields,
                ]);
            }

            $clone->terms()->sync($item->terms->pluck('id')->all());
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
        }
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
            ->sort()
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

                if (in_array($subType, ['image', 'file'], true)) {
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
                $dynamicField['name'] => "{$subField['label']} 檔案大小不可超過 {$maxSizeMb}MB。",
            ]);
        }

        if ($subType === 'image' && !str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw ValidationException::withMessages([
                $dynamicField['name'] => "{$subField['label']} 必須是圖片檔。",
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
                    $dynamicField['name'] => "{$subField['label']} 檔案格式必須是 {$format}。",
                ]);
            }
        }

        $path = $file->store("sites/{$siteId}/{$strategy}/dynamic/{$fileType}", 'public');
        [$width, $height] = $subType === 'image'
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

        return $item->attributesToArray();
    }

    private function emptyValues(array $config, string $locale): array
    {
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
}


