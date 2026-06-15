<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Services\HierarchicalTreeService;
use App\Services\TaxonomyTreeService;
use App\Support\AdminContext;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaxonomyTermController extends Controller
{
    public function __construct(
        private readonly TaxonomyTreeService $treeService,
        private readonly HierarchicalTreeService $hierarchy
    ) {
    }

    public function index(AdminContext $context, Request $request, string $taxonomy): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $taxonomyModel = $this->taxonomyForSite($site, $taxonomy);
        $parentId = $request->integer('parent_id') ?: null;
        $trash = $request->boolean('trash');
        $useHierarchy = (bool) $taxonomyModel->is_hierarchical;

        $parent = $parentId && $useHierarchy
            ? TaxonomyTerm::query()
                ->where('taxonomy_id', $taxonomyModel->id)
                ->where('locale', $site->default_locale)
                ->with('parent')
                ->findOrFail($parentId)
            : null;

        $termScope = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomyModel->id)
            ->where('locale', $site->default_locale)
            ->where(function (Builder $query) use ($parentId, $useHierarchy): void {
                if ($useHierarchy) {
                    $parentId ? $query->where('parent_id', $parentId) : $query->whereNull('parent_id');
                    return;
                }

                $query->whereNull('parent_id');
            });

        if ($trash) {
            $termScope->onlyTrashed();
        }

        if (!$trash) {
            $this->hierarchy->normalizeSortOrder($termScope);
        }

        return view('admin.taxonomies.index', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'taxonomy' => $taxonomy,
            'taxonomyModel' => $taxonomyModel,
            'parent' => $parent,
            'trash' => $trash,
            'trashCount' => TaxonomyTerm::query()->where('taxonomy_id', $taxonomyModel->id)->onlyTrashed()->count(),
            'sortOptionCount' => (clone $termScope)->count(),
            'terms' => $termScope
                ->withCount('children')
                ->with('parent')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate((int) $request->query('per_page', 12))
                ->withQueryString(),
        ]);
    }

    public function create(AdminContext $context, Request $request, string $taxonomy): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $taxonomyModel = $this->taxonomyForSite($site, $taxonomy);
        $parentId = $taxonomyModel->is_hierarchical ? ($request->integer('parent_id') ?: null) : null;

        return view('admin.taxonomies.form', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'taxonomy' => $taxonomy,
            'taxonomyModel' => $taxonomyModel,
            'term' => null,
            'parentOptions' => $taxonomyModel->is_hierarchical
                ? $this->treeService->flattenedOptions($taxonomyModel, $site->default_locale)->all()
                : [],
            'values' => ['parent_id' => $parentId, 'locale' => $site->default_locale, 'is_active' => true, 'sort_order' => 0],
        ]);
    }

    public function store(AdminContext $context, Request $request, string $taxonomy): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $taxonomyModel = $this->taxonomyForSite($site, $taxonomy);
        $data = $this->validatedData($request, $taxonomyModel->id, hierarchical: (bool) $taxonomyModel->is_hierarchical);
        $parentId = $taxonomyModel->is_hierarchical ? (($data['parent_id'] ?? null) ?: null) : null;

        TaxonomyTerm::query()->create([
            'taxonomy_id' => $taxonomyModel->id,
            'parent_id' => $parentId,
            'locale' => $data['locale'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($taxonomyModel->id, $data['locale'], $data['slug'] ?: $data['name']),
            'description' => $data['description'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => $this->nextSortOrder($taxonomyModel->id, $data['locale'], $parentId),
        ]);

        return redirect()
            ->route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $parentId]))
            ->with('status', '分類已建立。');
    }

    public function edit(AdminContext $context, string $taxonomy, TaxonomyTerm $term): View
    {
        $this->authorizeTerm($context, $taxonomy, $term);

        return view('admin.taxonomies.form', [
            'admin' => $context->user(),
            'site' => $context->site(),
            'sites' => $context->sites(),
            'taxonomy' => $taxonomy,
            'taxonomyModel' => $term->taxonomy,
            'term' => $term,
            'parentOptions' => $term->taxonomy->is_hierarchical
                ? $this->treeService->flattenedOptions($term->taxonomy, $term->locale, $term->id)->all()
                : [],
            'values' => $term->attributesToArray(),
        ]);
    }

    public function update(AdminContext $context, Request $request, string $taxonomy, TaxonomyTerm $term): RedirectResponse
    {
        $this->authorizeTerm($context, $taxonomy, $term);

        $data = $this->validatedData($request, $term->taxonomy_id, $term->id, (bool) $term->taxonomy->is_hierarchical);
        $parentId = $term->taxonomy->is_hierarchical ? (($data['parent_id'] ?? null) ?: null) : null;

        $term->update([
            'parent_id' => $parentId,
            'locale' => $data['locale'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($term->taxonomy_id, $data['locale'], $data['slug'] ?: $data['name'], $term->id),
            'description' => $data['description'] ?? null,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'is_active' => (bool) $data['is_active'],
        ]);

        $this->normalizeSiblingsAfterTreeChange($term);

        return redirect()->route('admin.taxonomies.edit', [$taxonomy, $term])->with('status', '分類已更新。');
    }

    public function sort(AdminContext $context, Request $request, string $taxonomy, TaxonomyTerm $term): JsonResponse
    {
        $this->authorizeTerm($context, $taxonomy, $term);

        $data = $request->validate(['sort_order' => ['required', 'integer', 'min:1']]);

        DB::transaction(function () use ($term, $data): void {
            $this->hierarchy->reorder($term, $this->siblingScope($term), (int) $data['sort_order']);
        });

        $term->refresh();

        return response()->json(['sort_order' => $term->sort_order]);
    }

    public function toggleStatus(AdminContext $context, string $taxonomy, TaxonomyTerm $term): JsonResponse
    {
        $this->authorizeTerm($context, $taxonomy, $term);

        $term->forceFill(['is_active' => !$term->is_active])->save();

        return response()->json([
            'is_active' => (bool) $term->is_active,
            'label' => $term->is_active ? '顯示' : '不顯示',
            'color' => $term->is_active ? '#28a745' : '#dc3545',
        ]);
    }

    public function destroy(AdminContext $context, string $taxonomy, TaxonomyTerm $term): RedirectResponse|JsonResponse
    {
        $this->authorizeTerm($context, $taxonomy, $term);
        $parentId = $term->parent_id;
        $termIds = $this->termTreeIds($term);

        if (!request()->boolean('confirm')) {
            $impact = $this->termDeleteImpact($termIds);
            if ($impact['total'] > 0) {
                return $this->taxonomyDeleteWarningResponse($impact, softDelete: true);
            }
        }

        DB::transaction(function () use ($term, $termIds): void {
            TaxonomyTerm::query()
                ->whereIn('id', $termIds)
                ->orderByDesc('id')
                ->get()
                ->each
                ->delete();

            $this->normalizeSiblingsAfterTreeChange($term);
        });

        $redirectUrl = route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $parentId]));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => '分類已移至垃圾桶。',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', '分類已移至垃圾桶。');
    }

    public function restore(AdminContext $context, string $taxonomy, int $id): RedirectResponse|JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $term = TaxonomyTerm::query()
            ->whereHas('taxonomy', fn (Builder $query) => $query->where('site_id', $site->id)->where('code', $taxonomy))
            ->onlyTrashed()
            ->findOrFail($id);

        DB::transaction(function () use ($term): void {
            TaxonomyTerm::withTrashed()
                ->whereIn('id', $this->termTreeIds($term, withTrashed: true))
                ->orderBy('id')
                ->get()
                ->each
                ->restore();

            $this->normalizeSiblingsAfterTreeChange($term);
        });

        $redirectUrl = route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $term->parent_id]));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => '分類已還原。',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', '分類已還原。');
    }

    public function forceDelete(AdminContext $context, string $taxonomy, int $id): RedirectResponse|JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $term = TaxonomyTerm::query()
            ->whereHas('taxonomy', fn (Builder $query) => $query->where('site_id', $site->id)->where('code', $taxonomy))
            ->onlyTrashed()
            ->findOrFail($id);

        $termIds = $this->termTreeIds($term, withTrashed: true);
        if (!request()->boolean('force')) {
            $impact = $this->termDeleteImpact($termIds, includeTrashedItems: true);
            if ($impact['total'] > 0) {
                return $this->taxonomyDeleteWarningResponse($impact, softDelete: false);
            }
        }

        DB::transaction(function () use ($term, $termIds): void {
            $this->forceDeleteTermTreeIds($termIds);
            $this->normalizeSiblingsAfterTreeChange($term);
        });

        $redirectUrl = route('admin.taxonomies.index', [$taxonomy, 'trash' => 1]);

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => '分類已永久刪除。',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', '分類已永久刪除。');
    }

    public function bulkAction(AdminContext $context, Request $request, string $taxonomy): JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $taxonomyModel = $this->taxonomyForSite($site, $taxonomy);

        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $query = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomyModel->id)
            ->whereIn('id', $data['ids']);

        if (in_array($data['action'], ['restore', 'force_delete'], true)) {
            $query->onlyTrashed();
        }

        $terms = $query->get();
        $affectedIds = [];

        foreach ($terms as $term) {
            $ids = $this->termTreeIds($term, withTrashed: in_array($data['action'], ['restore', 'force_delete'], true));
            $affectedIds = array_values(array_unique([...$affectedIds, ...$ids]));
        }

        if (in_array($data['action'], ['delete', 'force_delete'], true) && !$request->boolean($data['action'] === 'delete' ? 'confirm' : 'force')) {
            $impact = $this->termDeleteImpact($affectedIds, includeTrashedItems: $data['action'] === 'force_delete');
            if ($impact['total'] > 0) {
                return response()->json([
                    'ok' => false,
                    'needs_confirm' => $data['action'] === 'delete',
                    'needs_force' => $data['action'] === 'force_delete',
                    'title' => $data['action'] === 'delete' ? '分類內還有資料' : '分類仍有關聯資料',
                    'message' => $this->termDeleteImpactMessage($impact, $data['action'] === 'delete'),
                ]);
            }
        }

        DB::transaction(function () use ($terms, $data, &$affectedIds): void {
            if ($affectedIds === []) {
                return;
            }

            match ($data['action']) {
                'delete' => TaxonomyTerm::query()->whereIn('id', $affectedIds)->get()->each->delete(),
                'restore' => TaxonomyTerm::withTrashed()->whereIn('id', $affectedIds)->orderBy('id')->get()->each->restore(),
                'force_delete' => $this->forceDeleteTermTreeIds($affectedIds),
            };

            foreach ($terms as $term) {
                $this->normalizeSiblingsAfterTreeChange($term);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => $this->bulkActionMessage($data['action'], count($affectedIds)),
            'redirect_url' => $data['action'] === 'restore' ? route('admin.taxonomies.index', $taxonomy) : null,
        ]);
    }

    private function validatedData(Request $request, int $taxonomyId, ?int $ignoreId = null, bool $hierarchical = true): array
    {
        $rules = [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('taxonomy_terms', 'id')->where('taxonomy_id', $taxonomyId),
            ],
            'locale' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];

        if (!$hierarchical) {
            $rules['parent_id'] = ['nullable'];
        }

        return $request->validate($rules);
    }

    private function authorizeTerm(AdminContext $context, string $taxonomy, TaxonomyTerm $term): void
    {
        $site = $context->site();
        abort_unless($site && $term->taxonomy?->site_id === $site->id && $term->taxonomy?->code === $taxonomy, 404);
    }

    private function taxonomyForSite(Site $site, string $taxonomy): Taxonomy
    {
        $config = $this->taxonomyConfig($taxonomy);

        return $this->treeService->taxonomyForSite(
            $site,
            $taxonomy,
            $config['taxonomy_label'] ?? $this->label($taxonomy),
            (bool) ($config['taxonomy_hierarchical'] ?? true)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function taxonomyConfig(string $taxonomy): array
    {
        foreach (CmsSetLoader::all('list') as $config) {
            if (($config['taxonomy'] ?? null) === $taxonomy) {
                return $config;
            }
        }

        return [];
    }

    private function nextSortOrder(int $taxonomyId, string $locale, ?int $parentId): int
    {
        return ((int) TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('locale', $locale)
            ->where('parent_id', $parentId)
            ->max('sort_order')) + 1;
    }

    private function siblingScope(TaxonomyTerm $term): Builder
    {
        return TaxonomyTerm::query()
            ->where('taxonomy_id', $term->taxonomy_id)
            ->where('locale', $term->locale)
            ->where(function (Builder $query) use ($term): void {
                $term->parent_id
                    ? $query->where('parent_id', $term->parent_id)
                    : $query->whereNull('parent_id');
            });
    }

    /**
     * @return array<int>
     */
    private function termTreeIds(TaxonomyTerm $term, bool $withTrashed = false): array
    {
        $query = TaxonomyTerm::query()
            ->where('taxonomy_id', $term->taxonomy_id)
            ->where('locale', $term->locale);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $this->hierarchy
            ->descendantIds($query->get(['id', 'parent_id']), $term->id)
            ->push($term->id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int> $ids
     */
    private function detachTermRelations(array $ids): void
    {
        DB::table('content_term')->whereIn('taxonomy_term_id', $ids)->delete();
        DB::table('product_category_product')->whereIn('taxonomy_term_id', $ids)->delete();
    }

    private function termDeleteImpact(array $ids, bool $includeTrashedItems = false): array
    {
        if ($ids === []) {
            return ['content' => 0, 'product' => 0, 'total' => 0];
        }

        $contentQuery = DB::table('content_term')
            ->join('contents', 'contents.id', '=', 'content_term.content_id')
            ->whereIn('content_term.taxonomy_term_id', $ids);

        $productQuery = DB::table('product_category_product')
            ->join('products', 'products.id', '=', 'product_category_product.product_id')
            ->whereIn('product_category_product.taxonomy_term_id', $ids);

        if (!$includeTrashedItems) {
            $contentQuery->whereNull('contents.deleted_at');
            $productQuery->whereNull('products.deleted_at');
        }

        $contentCount = (int) $contentQuery->distinct('contents.id')->count('contents.id');
        $productCount = (int) $productQuery->distinct('products.id')->count('products.id');

        return [
            'content' => $contentCount,
            'product' => $productCount,
            'total' => $contentCount + $productCount,
        ];
    }

    private function taxonomyDeleteWarningResponse(array $impact, bool $softDelete): RedirectResponse|JsonResponse
    {
        if (request()->expectsJson()) {
            return response()->json([
                'ok' => false,
                'needs_confirm' => $softDelete,
                'needs_force' => !$softDelete,
                'title' => $softDelete ? '分類內還有資料' : '分類仍有關聯資料',
                'message' => $this->termDeleteImpactMessage($impact, $softDelete),
            ], 409);
        }

        return back()->withErrors([$this->termDeleteImpactMessage($impact, $softDelete)]);
    }

    private function termDeleteImpactMessage(array $impact, bool $softDelete): string
    {
        $lines = [];
        if ($impact['content'] > 0) {
            $lines[] = "內容資料：{$impact['content']} 筆";
        }
        if ($impact['product'] > 0) {
            $lines[] = "產品資料：{$impact['product']} 筆";
        }

        $description = $softDelete
            ? '分類會移至垃圾桶，資料不會被刪除，還原分類後關聯會保留。'
            : '分類會永久刪除，資料不會被刪除，但會解除這些分類關聯。';

        return implode("\n", $lines)."\n\n".$description;
    }

    /**
     * @param array<int> $ids
     */
    private function forceDeleteTermTreeIds(array $ids): void
    {
        $ids = array_values(array_unique($ids));
        $this->detachTermRelations($ids);

        TaxonomyTerm::withTrashed()
            ->whereIn('id', $ids)
            ->orderByDesc('id')
            ->get()
            ->each
            ->forceDelete();
    }

    private function normalizeSiblingsAfterTreeChange(TaxonomyTerm $term): void
    {
        $this->hierarchy->normalizeSortOrder($this->siblingScope($term));
    }

    private function bulkActionMessage(string $action, int $count): string
    {
        return match ($action) {
            'delete' => "已移至垃圾桶 {$count} 筆分類。",
            'restore' => "已還原 {$count} 筆分類。",
            'force_delete' => "已永久刪除 {$count} 筆分類。",
            default => "已處理 {$count} 筆分類。",
        };
    }

    private function uniqueSlug(int $taxonomyId, string $locale, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $candidate = $base;
        $counter = 2;

        while (
            TaxonomyTerm::query()
                ->where('taxonomy_id', $taxonomyId)
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

    private function label(string $taxonomy): string
    {
        return match ($taxonomy) {
            'newsCate' => '最新消息分類',
            'productCate' => '產品分類',
            default => $taxonomy,
        };
    }
}
