<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\HomeDisplay;
use App\Support\AdminContext;
use App\Support\AdminLanguage;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeDisplayController extends Controller
{
    public function index(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $config = $this->config();
        $languageContext = AdminLanguage::context($site, $request);
        $locale = $languageContext['locale'];
        $targetModule = $this->targetModule($config);
        $targetContentType = $this->targetContentType($config);
        $targetResource = $this->targetResource($config);
        $keyword = trim((string) $request->query('search', ''));

        $query = $this->contentQuery($site->id, $targetContentType, $locale)
            ->when($keyword !== '', fn (Builder $builder) => $builder->whereHas('translations', fn (Builder $translation) => $translation
                ->where('locale', $locale)
                ->where(function (Builder $q) use ($keyword): void {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('summary', 'like', "%{$keyword}%");
                })))
            ->leftJoin('home_displays as hd', function ($join) use ($site, $targetModule, $locale): void {
                $join->on('hd.content_id', '=', 'contents.id')
                    ->where('hd.site_id', $site->id)
                    ->where('hd.module', $targetModule)
                    ->where('hd.locale', $locale);
            })
            ->select('contents.*')
            ->selectRaw('hd.id as home_display_id, hd.sort_order as home_sort_order, hd.is_active as home_is_active')
            ->orderByRaw('case when hd.id is null then 1 else 0 end')
            ->orderByRaw('coalesce(hd.sort_order, 9999)')
            ->latest('contents.published_at')
            ->latest('contents.updated_at');

        $items = $query
            ->paginate((int) $request->query('per_page', data_get($config, 'listPage.itemsPerPage', 12)))
            ->withQueryString();

        return view('admin.home-display.index', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'config' => $config,
            'targetModule' => $targetModule,
            'targetResource' => $targetResource,
            'languageContext' => $languageContext,
            'languageParams' => ['language' => $languageContext['slug']],
            'keyword' => $keyword,
            'items' => $items,
            'displayCount' => $this->displayCount($site->id, $targetModule, $locale),
        ]);
    }

    public function toggle(AdminContext $context, Request $request, Content $content): JsonResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $content->site_id === (int) $site->id, 404);

        $config = $this->config();
        $targetModule = $this->targetModule($config);
        $targetContentType = $this->targetContentType($config);
        $locale = AdminLanguage::context($site, $request)['locale'];
        $this->authorizeContentModule($content, $site->id, $targetContentType);

        $display = HomeDisplay::query()
            ->where('site_id', $site->id)
            ->where('module', $targetModule)
            ->where('locale', $locale)
            ->where('content_id', $content->id)
            ->first();

        if ($display) {
            $display->delete();
            $this->normalizeSort($site->id, $targetModule, $locale);

            return response()->json([
                'ok' => true,
                'is_in_home' => false,
                'display_count' => $this->displayCount($site->id, $targetModule, $locale),
            ]);
        }

        HomeDisplay::query()->create([
            'site_id' => $site->id,
            'content_id' => $content->id,
            'module' => $targetModule,
            'locale' => $locale,
            'is_active' => true,
            'sort_order' => $this->displayCount($site->id, $targetModule, $locale) + 1,
        ]);

        return response()->json([
            'ok' => true,
            'is_in_home' => true,
            'display_count' => $this->displayCount($site->id, $targetModule, $locale),
        ]);
    }

    public function sort(AdminContext $context, Request $request, Content $content): JsonResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $content->site_id === (int) $site->id, 404);

        $config = $this->config();
        $targetModule = $this->targetModule($config);
        $targetContentType = $this->targetContentType($config);
        $locale = AdminLanguage::context($site, $request)['locale'];
        $this->authorizeContentModule($content, $site->id, $targetContentType);

        $data = $request->validate([
            'sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $display = HomeDisplay::query()
            ->where('site_id', $site->id)
            ->where('module', $targetModule)
            ->where('locale', $locale)
            ->where('content_id', $content->id)
            ->firstOrFail();

        DB::transaction(function () use ($display, $site, $targetModule, $locale, $data): void {
            $displays = HomeDisplay::query()
                ->where('site_id', $site->id)
                ->where('module', $targetModule)
                ->where('locale', $locale)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $ids = $displays
                ->pluck('id')
                ->reject(fn ($id) => (int) $id === (int) $display->id)
                ->values()
                ->all();
            $targetIndex = max(0, min((int) $data['sort_order'] - 1, count($ids)));
            array_splice($ids, $targetIndex, 0, [$display->id]);

            foreach (array_values($ids) as $index => $id) {
                HomeDisplay::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        $display->refresh();

        return response()->json([
            'ok' => true,
            'sort_order' => $display->sort_order,
            'display_count' => $this->displayCount($site->id, $targetModule, $locale),
        ]);
    }

    private function config(): array
    {
        $config = CmsSetLoader::get('homeDisplay', 'home_display');
        abort_unless($config, 404);

        return $config;
    }

    private function targetModule(array $config): string
    {
        $module = $config['targetModule'] ?? null;
        abort_unless(is_string($module) && $module !== '', 500, 'homeDisplaySet.php missing targetModule.');

        return $module;
    }

    private function targetContentType(array $config): string
    {
        return (string) ($config['targetContentType'] ?? $this->targetModule($config));
    }

    private function targetResource(array $config): string
    {
        return (string) ($config['targetResource'] ?? $this->targetModule($config));
    }

    private function contentQuery(int $siteId, string $module, string $locale): Builder
    {
        $contentTypeId = ContentType::query()
            ->where('site_id', $siteId)
            ->where('code', $module)
            ->value('id');

        abort_unless($contentTypeId, 404);

        return Content::query()
            ->where('contents.site_id', $siteId)
            ->where('contents.content_type_id', $contentTypeId)
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale))
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)]);
    }

    private function authorizeContentModule(Content $content, int $siteId, string $module): void
    {
        $contentTypeId = ContentType::query()
            ->where('site_id', $siteId)
            ->where('code', $module)
            ->value('id');

        abort_unless((int) $content->content_type_id === (int) $contentTypeId, 404);
    }

    private function displayCount(int $siteId, string $module, string $locale): int
    {
        return (int) HomeDisplay::query()
            ->where('site_id', $siteId)
            ->where('module', $module)
            ->where('locale', $locale)
            ->count();
    }

    private function normalizeSort(int $siteId, string $module, string $locale): void
    {
        HomeDisplay::query()
            ->where('site_id', $siteId)
            ->where('module', $module)
            ->where('locale', $locale)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (HomeDisplay $display, int $index): void {
                $display->forceFill(['sort_order' => $index + 1])->save();
            });
    }
}
