<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsMenu;
use App\Services\HierarchicalTreeService;
use App\Support\AdminContext;
use App\Support\AdminLanguage;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmsMenuController extends Controller
{
    public function __construct(private readonly HierarchicalTreeService $tree)
    {
    }

    public function index(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $location = $request->query('location', 'backend');
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = $this->locationUsesLanguage($location);
        $locale = $languageEnabled ? $languageContext['locale'] : $site->default_locale;
        $trash = $request->boolean('trash');
        $parentId = $request->integer('parent_id') ?: null;
        $parent = $parentId
            ? CmsMenu::query()
                ->where('site_id', $site->id)
                ->where('location', $location)
                ->when($languageEnabled, fn (Builder $query) => $query->where('locale', $locale))
                ->with('parent')
                ->findOrFail($parentId)
            : null;

        $scope = CmsMenu::query()
            ->where('site_id', $site->id)
            ->where('location', $location)
            ->when($languageEnabled, fn (Builder $query) => $query->where('locale', $locale))
            ->where(function (Builder $query) use ($parentId): void {
                $parentId ? $query->where('parent_id', $parentId) : $query->whereNull('parent_id');
            });
        if ($trash) {
            $scope->onlyTrashed();
        }

        if (!$trash) {
            $this->tree->normalizeSortOrder($scope);
        }

        $keyword = trim((string) $request->query('keyword', ''));
        $menus = (clone $scope)
            ->when($keyword !== '', fn (Builder $query) => $query->where('title', 'like', "%{$keyword}%"))
            ->withCount('children')
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate((int) $request->query('per_page', 12))
            ->withQueryString();

        return view('admin.menus.index', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'location' => $location,
            'parent' => $parent,
            'menus' => $menus,
            'trash' => $trash,
            'trashCount' => CmsMenu::query()
                ->where('site_id', $site->id)
                ->where('location', $location)
                ->when($languageEnabled, fn (Builder $query) => $query->where('locale', $locale))
                ->onlyTrashed()
                ->count(),
            'sortOptionCount' => (clone $scope)->count(),
            'keyword' => $keyword,
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
            'languageParams' => $this->languageRouteParams($languageContext, $languageEnabled),
        ]);
    }

    public function create(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $location = $request->query('location', 'backend');
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = $this->locationUsesLanguage($location);
        $locale = $languageEnabled ? $languageContext['locale'] : $site->default_locale;
        $parentId = $request->integer('parent_id') ?: null;

        return view('admin.menus.form', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'menu' => null,
            'location' => $location,
            'parentOptions' => $this->parentOptions($site->id, $location, null, $languageEnabled ? $locale : null),
            'values' => [
                'location' => $location,
                'locale' => $locale,
                'parent_id' => $parentId,
                'type' => 'custom',
                'target' => '_self',
                'is_active' => true,
            ],
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
            'languageParams' => $this->languageRouteParams($languageContext, $languageEnabled),
        ]);
    }

    public function store(AdminContext $context, Request $request): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = $this->locationUsesLanguage((string) $request->input('location', 'backend'));
        $data = $this->validatedData($request, $site->id);
        $data['locale'] = $languageEnabled ? $languageContext['locale'] : $site->default_locale;
        $parentId = ($data['parent_id'] ?? null) ?: null;

        CmsMenu::query()->create([
            ...$data,
            'site_id' => $site->id,
            'parent_id' => $parentId,
            'is_active' => (bool) $data['is_active'],
            'sort_order' => $this->nextSortOrder($site->id, $data['location'], $parentId, $languageEnabled ? $data['locale'] : null),
        ]);

        return redirect()
            ->route('admin.menus.index', array_filter(['location' => $data['location'], 'parent_id' => $parentId, ...$this->languageRouteParams($languageContext, $languageEnabled)]))
            ->with('status', "\u{9078}\u{55AE}\u{5DF2}\u{65B0}\u{589E}");
    }

    public function edit(AdminContext $context, CmsMenu $menu): View
    {
        $site = $context->site();
        abort_unless($site && (int) $menu->site_id === (int) $site->id, 404);
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = $this->locationUsesLanguage($menu->location);

        return view('admin.menus.form', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'menu' => $menu,
            'location' => $menu->location,
            'parentOptions' => $this->parentOptions($site->id, $menu->location, $menu->id, $languageEnabled ? $menu->locale : null),
            'values' => $menu->attributesToArray(),
            'languageContext' => $languageContext,
            'languageEnabled' => $languageEnabled,
            'languageParams' => $this->languageRouteParams($languageContext, $languageEnabled),
        ]);
    }

    public function update(AdminContext $context, Request $request, CmsMenu $menu): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $menu->site_id === (int) $site->id, 404);

        $oldScope = $this->siblingScope($menu);
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = $this->locationUsesLanguage((string) $request->input('location', $menu->location));
        $data = $this->validatedData($request, $site->id, $menu->id);
        $data['locale'] = $languageEnabled ? $languageContext['locale'] : $site->default_locale;
        $parentId = ($data['parent_id'] ?? null) ?: null;

        $menu->update([
            ...$data,
            'parent_id' => $parentId,
            'is_active' => (bool) $data['is_active'],
        ]);

        $this->tree->normalizeSortOrder($oldScope);
        $this->tree->normalizeSortOrder($this->siblingScope($menu->refresh()));

        return redirect()->route('admin.menus.edit', [$menu, ...$this->languageRouteParams($languageContext, $languageEnabled)])->with('status', "\u{9078}\u{55AE}\u{5DF2}\u{66F4}\u{65B0}");
    }

    public function sort(AdminContext $context, Request $request, CmsMenu $menu): JsonResponse
    {
        $this->authorizeMenu($context, $menu);
        $data = $request->validate(['sort_order' => ['required', 'integer', 'min:1']]);

        DB::transaction(fn () => $this->tree->reorder($menu, $this->siblingScope($menu), (int) $data['sort_order']));
        $menu->refresh();

        return response()->json(['sort_order' => $menu->sort_order]);
    }

    public function toggleStatus(AdminContext $context, CmsMenu $menu): JsonResponse
    {
        $this->authorizeMenu($context, $menu);
        $menu->forceFill(['is_active' => !$menu->is_active])->save();

        return response()->json([
            'is_active' => (bool) $menu->is_active,
            'label' => $menu->is_active ? "\u{986F}\u{793A}" : "\u{4E0D}\u{986F}\u{793A}",
            'color' => $menu->is_active ? '#28a745' : '#dc3545',
        ]);
    }

    public function destroy(AdminContext $context, CmsMenu $menu): RedirectResponse|JsonResponse
    {
        $this->authorizeMenu($context, $menu);
        $site = $context->site();
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = $this->locationUsesLanguage($menu->location);
        $redirect = ['location' => $menu->location, 'parent_id' => $menu->parent_id, ...$this->languageRouteParams($languageContext, $languageEnabled)];
        $scope = $this->siblingScope($menu);

        DB::transaction(function () use ($menu, $scope): void {
            $this->deleteWithDescendants($menu);
            $this->tree->normalizeSortOrder($scope);
        });

        $redirectUrl = route('admin.menus.index', array_filter($redirect));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "\u{9078}\u{55AE}\u{5DF2}\u{522A}\u{9664}",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "\u{9078}\u{55AE}\u{5DF2}\u{522A}\u{9664}");
    }

    public function restore(AdminContext $context, int $id): RedirectResponse|JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);
        $menu = CmsMenu::query()->where('site_id', $site->id)->onlyTrashed()->findOrFail($id);
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = $this->locationUsesLanguage($menu->location);
        $menu->restore();

        $redirectUrl = route('admin.menus.index', array_filter(['location' => $menu->location, 'parent_id' => $menu->parent_id, ...$this->languageRouteParams($languageContext, $languageEnabled)]));

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "\u{9078}\u{55AE}\u{5DF2}\u{9084}\u{539F}",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "\u{9078}\u{55AE}\u{5DF2}\u{9084}\u{539F}");
    }

    public function forceDelete(AdminContext $context, int $id): RedirectResponse|JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);
        $menu = CmsMenu::query()->where('site_id', $site->id)->onlyTrashed()->findOrFail($id);
        $location = $menu->location;
        $languageContext = AdminLanguage::context($site, request());
        $languageEnabled = $this->locationUsesLanguage($location);
        $menu->forceDelete();

        $redirectUrl = route('admin.menus.index', ['location' => $location, 'trash' => 1, ...$this->languageRouteParams($languageContext, $languageEnabled)]);

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => "\u{9078}\u{55AE}\u{5DF2}\u{6C38}\u{4E45}\u{522A}\u{9664}",
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect($redirectUrl)->with('status', "\u{9078}\u{55AE}\u{5DF2}\u{6C38}\u{4E45}\u{522A}\u{9664}");
    }

    public function bulkAction(AdminContext $context, Request $request): JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $menusQuery = CmsMenu::query()
            ->where('site_id', $site->id)
            ->whereIn('id', $data['ids']);
        if (in_array($data['action'], ['restore', 'force_delete'], true)) {
            $menusQuery->onlyTrashed();
        }

        $menus = $menusQuery->get();
        $firstMenu = $menus->first();
        $languageContext = AdminLanguage::context($site, $request);
        $languageEnabled = $firstMenu ? $this->locationUsesLanguage($firstMenu->location) : false;

        DB::transaction(function () use ($menus, $data): void {
            foreach ($menus as $menu) {
                match ($data['action']) {
                    'delete' => $this->deleteWithDescendants($menu),
                    'restore' => $menu->restore(),
                    'force_delete' => $menu->forceDelete(),
                };
            }
        });

        return response()->json([
            'ok' => true,
            'message' => $this->bulkActionMessage($data['action'], $menus->count()),
            'redirect_url' => $data['action'] === 'restore'
                ? route('admin.menus.index', ['location' => $firstMenu?->location ?? 'backend', ...$this->languageRouteParams($languageContext, $languageEnabled)])
                : null,
        ]);
    }

    private function bulkActionMessage(string $action, int $count): string
    {
        return match ($action) {
            'delete' => "已移至垃圾桶 {$count} 筆選單。",
            'restore' => "已還原 {$count} 筆選單。",
            'force_delete' => "已永久刪除 {$count} 筆選單。",
            default => "已處理 {$count} 筆選單。",
        };
    }

    private function validatedData(Request $request, int $siteId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_menus', 'id')->where('site_id', $siteId),
            ],
            'location' => ['required', 'string', 'max:50'],
            'locale' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['custom', 'module', 'route'])],
            'url' => ['nullable', 'string', 'max:500'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'module_key' => ['nullable', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'target' => ['required', Rule::in(['_self', '_blank'])],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function parentOptions(int $siteId, string $location, ?int $excludeId = null, ?string $locale = null): array
    {
        $menus = CmsMenu::query()
            ->where('site_id', $siteId)
            ->where('location', $location)
            ->when($locale, fn (Builder $query) => $query->where('locale', $locale))
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return $this->tree->flattenedOptions($menus, $excludeId);
    }

    private function authorizeMenu(AdminContext $context, CmsMenu $menu): void
    {
        $site = $context->site();
        abort_unless($site && (int) $menu->site_id === (int) $site->id, 404);
    }

    private function siblingScope(CmsMenu $menu): Builder
    {
        return CmsMenu::query()
            ->where('site_id', $menu->site_id)
            ->where('location', $menu->location)
            ->when($this->locationUsesLanguage($menu->location), fn (Builder $query) => $query->where('locale', $menu->locale))
            ->where(function (Builder $query) use ($menu): void {
                $menu->parent_id ? $query->where('parent_id', $menu->parent_id) : $query->whereNull('parent_id');
            });
    }

    private function nextSortOrder(int $siteId, string $location, ?int $parentId, ?string $locale = null): int
    {
        return ((int) CmsMenu::query()
            ->where('site_id', $siteId)
            ->where('location', $location)
            ->when($this->locationUsesLanguage($location) && $locale, fn (Builder $query) => $query->where('locale', $locale))
            ->where('parent_id', $parentId)
            ->max('sort_order')) + 1;
    }

    private function locationUsesLanguage(string $location): bool
    {
        $config = CmsSetLoader::get('menus', 'menu') ?? [];
        $locations = $config['languageLocations'] ?? [];

        if (array_key_exists($location, $locations)) {
            return (bool) $locations[$location];
        }

        return (bool) ($config['hasLanguage'] ?? $config['languageEnabled'] ?? false);
    }

    private function languageRouteParams(array $languageContext, bool $enabled): array
    {
        return $enabled ? ['language' => $languageContext['slug']] : [];
    }

    private function deleteWithDescendants(CmsMenu $menu): void
    {
        $menu->children()->get()->each(fn (CmsMenu $child) => $this->deleteWithDescendants($child));
        $menu->delete();
    }
}
