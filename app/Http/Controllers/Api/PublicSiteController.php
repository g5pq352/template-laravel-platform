<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CmsMenu;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentTranslation;
use App\Models\HomeDisplay;
use App\Models\Language;
use App\Models\LanguagePack;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\Site;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Support\ApiMediaFormatter;
use App\Support\CmsSetLoader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicSiteController extends Controller
{
    public function __construct(private readonly ApiMediaFormatter $mediaFormatter) {}

    public function site(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);

        return response()->json([
            'data' => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'locale' => $locale,
                'default_locale' => $site->default_locale,
                'timezone' => $site->timezone,
                'currency_code' => $site->currency_code,
                'settings' => $site->settings ?? [],
            ],
        ]);
    }

    public function sites(Request $request): JsonResponse
    {
        $currentSite = $this->siteFromRequest($request);

        $sites = Site::query()
            ->where('tenant_id', $currentSite->tenant_id)
            ->where('status', 'active')
            ->with(['domains' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('domain')])
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'default_locale' => $site->default_locale,
                'timezone' => $site->timezone,
                'currency_code' => $site->currency_code,
                'primary_domain' => $site->domains->firstWhere('is_primary', true)?->domain
                    ?? $site->domains->first()?->domain,
                'domains' => $site->domains
                    ->map(fn ($domain) => [
                        'domain' => $domain->domain,
                        'is_primary' => (bool) $domain->is_primary,
                        'force_https' => (bool) $domain->force_https,
                    ])
                    ->values(),
                'is_current' => (int) $site->id === (int) $currentSite->id,
            ])
            ->values();

        return response()->json(['data' => $sites]);
    }

    public function languages(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);

        return response()->json([
            'data' => $this->languagesForSite($site)
                ->map(fn (Language $language) => [
                    'name' => $language->name,
                    'name_en' => $language->name_en,
                    'slug' => $language->slug,
                    'locale' => $language->locale,
                    'is_default' => $language->is_default,
                ])
                ->values(),
        ]);
    }

    public function languagePacks(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);
        $cacheKey = $this->cacheKey($site, $locale, 'language_packs');

        $packs = Cache::remember($cacheKey, now()->addMinutes(10), fn () => LanguagePack::query()
            ->where('site_id', $site->id)
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('sort_order')
            ->orderBy('key')
            ->get()
            ->mapWithKeys(fn (LanguagePack $pack) => [
                $pack->key => $pack->translationValue($locale) ?? '',
            ])
            ->all());

        return response()->json(['data' => $packs]);
    }

    public function homeDisplay(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);
        $config = CmsSetLoader::get('homeDisplay', 'home_display') ?? [];
        $module = (string) ($config['targetModule'] ?? 'news');
        $targetType = (string) ($config['targetContentType'] ?? $module);
        $limit = min(max((int) $request->query('limit', 12), 1), 100);
        $cacheKey = $this->cacheKey($site, "{$locale}:{$module}:{$targetType}:{$limit}", 'home_display');

        $items = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($site, $locale, $module, $targetType, $limit): array {
            $contentTypeId = ContentType::query()
                ->where('site_id', $site->id)
                ->where('code', $targetType)
                ->value('id');

            if (!$contentTypeId) {
                return [];
            }

            return HomeDisplay::query()
                ->where('site_id', $site->id)
                ->where('module', $module)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->whereHas('content', fn (Builder $query) => $query
                    ->where('site_id', $site->id)
                    ->where('content_type_id', $contentTypeId)
                    ->where('status', 'published')
                    ->whereHas('translations', fn (Builder $translation) => $translation->where('locale', $locale)))
                ->with([
                    'content.terms',
                    'content.translations' => fn ($query) => $query->where('locale', $locale),
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (HomeDisplay $display) => array_merge(
                    $this->contentPayload($display->content, $locale, false),
                    ['home_sort_order' => $display->sort_order]
                ))
                ->values()
                ->all();
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'module' => $module,
                'target_type' => $targetType,
                'locale' => $locale,
            ],
        ]);
    }

    public function menus(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);
        $location = (string) $request->query('location', 'main');
        $usesLanguage = $this->menuLocationUsesLanguage($location);
        $cacheKey = $this->cacheKey($site, ($usesLanguage ? "{$locale}:" : '') . $location, 'menus');

        $menus = Cache::remember($cacheKey, now()->addMinutes(10), fn () => CmsMenu::query()
            ->where('site_id', $site->id)
            ->where('location', $location)
            ->when($usesLanguage, fn (Builder $query) => $query->where('locale', $locale))
            ->where('is_active', true)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (CmsMenu $menu) => $this->menuPayload($menu))
            ->values()
            ->all());

        return response()->json(['data' => $menus]);
    }

    public function contents(Request $request, string $type): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

        $contentType = ContentType::query()
            ->where('site_id', $site->id)
            ->where('code', $type)
            ->firstOrFail();

        $query = Content::query()
            ->where('site_id', $site->id)
            ->where('content_type_id', $contentType->id)
            ->where('status', 'published')
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale))
            ->with([
                'terms',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('published_at');

        if ($term = $request->query('term')) {
            $query->whereHas('terms', fn (Builder $termQuery) => $termQuery->where('taxonomy_terms.slug', $term));
        }

        return response()->json($query->paginate($perPage)->through(
            fn (Content $content) => $this->contentPayload($content, $locale, false)
        ));
    }

    public function content(Request $request, string $type, string $slug): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);

        $contentType = ContentType::query()
            ->where('site_id', $site->id)
            ->where('code', $type)
            ->firstOrFail();

        $content = Content::query()
            ->where('site_id', $site->id)
            ->where('content_type_id', $contentType->id)
            ->where('status', 'published')
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale)->where('slug', $slug))
            ->with([
                'terms',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->firstOrFail();

        $translation = $content->translation($locale);
        if ($translation && $request->boolean('track_view', true) && $this->trackContentViewCount($translation, $site, $request)) {
            $translation->view_count = ((int) $translation->view_count) + 1;
        }

        return response()->json(['data' => $this->contentPayload($content, $locale, true)]);
    }

    public function trackContentView(Request $request, string $type, string $slug): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);

        $contentType = ContentType::query()
            ->where('site_id', $site->id)
            ->where('code', $type)
            ->firstOrFail();

        $content = Content::query()
            ->where('site_id', $site->id)
            ->where('content_type_id', $contentType->id)
            ->where('status', 'published')
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale)->where('slug', $slug))
            ->firstOrFail();

        $translation = $content->translations()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->firstOrFail();
        $counted = $this->trackContentViewCount($translation, $site, $request);
        if ($counted) {
            $translation->view_count = ((int) $translation->view_count) + 1;
        }

        return response()->json([
            'data' => [
                'id' => $content->id,
                'view_count' => $translation->view_count,
                'counted' => $counted,
            ],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);

        $query = Product::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale))
            ->with([
                'categories',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('published_at');

        if ($category = $request->query('category')) {
            $query->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->where('taxonomy_terms.slug', $category));
        }

        return response()->json($query->paginate($perPage)->through(
            fn (Product $product) => $this->productPayload($product, $locale, false)
        ));
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);

        $product = Product::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->whereHas('translations', fn (Builder $query) => $query->where('locale', $locale)->where('slug', $slug))
            ->with([
                'categories',
                'translations' => fn ($query) => $query->where('locale', $locale),
            ])
            ->firstOrFail();

        $translation = $product->translation($locale);
        if ($translation && $request->boolean('track_view', true) && $this->trackProductViewCount($translation, $site, $request)) {
            $translation->view_count = ((int) $translation->view_count) + 1;
        }

        return response()->json(['data' => $this->productPayload($product, $locale, true)]);
    }

    public function taxonomy(Request $request, string $code): JsonResponse
    {
        $site = $this->siteFromRequest($request);
        $locale = $this->localeFromRequest($request, $site);

        $taxonomy = Taxonomy::query()
            ->where('site_id', $site->id)
            ->where('code', $code)
            ->firstOrFail();

        $terms = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TaxonomyTerm $term) => $this->termPayload($term))
            ->values();

        return response()->json([
            'data' => [
                'code' => $taxonomy->code,
                'name' => $taxonomy->name,
                'is_hierarchical' => $taxonomy->is_hierarchical,
                'terms' => $terms,
            ],
        ]);
    }

    private function siteFromRequest(Request $request): Site
    {
        return $request->attributes->get('site');
    }

    private function localeFromRequest(Request $request, Site $site): string
    {
        $requested = $request->query('locale')
            ?: $request->query('language')
            ?: $request->header('X-Language');

        $languages = $this->languagesForSite($site);
        $language = $languages->first(fn (Language $language) => in_array($requested, [$language->slug, $language->locale], true));

        return $language?->locale ?? $languages->firstWhere('is_default', true)?->locale ?? $site->default_locale;
    }

    private function languagesForSite(Site $site)
    {
        return Language::query()
            ->where('site_id', $site->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function menuPayload(CmsMenu $menu): array
    {
        return [
            'title' => $menu->title,
            'type' => $menu->type,
            'url' => $menu->url,
            'target' => $menu->target,
            'module_key' => $menu->module_key,
            'settings' => $menu->settings ?? [],
            'children' => $menu->children
                ->where('is_active', true)
                ->map(fn (CmsMenu $child) => $this->menuPayload($child))
                ->values(),
        ];
    }

    private function contentPayload(Content $content, string $locale, bool $detail): array
    {
        $translation = $content->translation($locale);
        $customFields = $this->mediaFormatter->normalizeCustomFields($translation?->custom_fields ?? []);
        $media = $this->mediaFormatter->mediaFor($content, 'content');

        return [
            'id' => $content->id,
            'status' => $content->status,
            'is_pinned' => $content->is_pinned,
            'view_count' => $translation?->view_count ?? 0,
            'published_at' => $content->published_at?->toISOString(),
            'title' => $translation?->title,
            'slug' => $translation?->slug,
            'summary' => $translation?->summary,
            'body' => $detail ? $translation?->body : null,
            'seo' => [
                'title' => $translation?->seo_title,
                'description' => $translation?->seo_description,
            ],
            'custom_fields' => $customFields,
            'media' => $media,
            'media_by_role' => $this->mediaFormatter->groupByRole($media),
            'terms' => $content->terms->map(fn (TaxonomyTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'slug' => $term->slug,
            ])->values(),
        ];
    }

    private function trackContentViewCount(ContentTranslation $translation, Site $site, Request $request): bool
    {
        if (!$this->reserveViewCountSlot('content', $site->id, $translation->id, $translation->locale, $request)) {
            return false;
        }

        ContentTranslation::query()->whereKey($translation->getKey())->increment('view_count');

        return true;
    }

    private function trackProductViewCount(ProductTranslation $translation, Site $site, Request $request): bool
    {
        if (!$this->reserveViewCountSlot('product', $site->id, $translation->id, $translation->locale, $request)) {
            return false;
        }

        ProductTranslation::query()->whereKey($translation->getKey())->increment('view_count');

        return true;
    }

    private function reserveViewCountSlot(string $type, int $siteId, int $itemId, string $locale, Request $request): bool
    {
        $fingerprint = sha1(implode('|', [
            $request->ip() ?: 'unknown',
            (string) $request->userAgent(),
        ]));

        return Cache::add(
            "view_count:{$type}:{$siteId}:{$itemId}:{$locale}:{$fingerprint}",
            true,
            now()->addMinutes(30)
        );
    }

    private function productPayload(Product $product, string $locale, bool $detail): array
    {
        $translation = $product->translation($locale);
        $media = $this->mediaFormatter->mediaFor($product, 'product');

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'status' => $product->status,
            'product_type' => $product->product_type,
            'view_count' => $translation?->view_count ?? 0,
            'price' => [
                'base' => $product->base_price,
                'compare_at' => $product->compare_at_price,
                'currency' => $product->currency_code,
            ],
            'published_at' => $product->published_at?->toISOString(),
            'name' => $translation?->name,
            'slug' => $translation?->slug,
            'summary' => $translation?->summary,
            'description' => $detail ? $translation?->description : null,
            'seo' => [
                'title' => $translation?->seo_title,
                'description' => $translation?->seo_description,
            ],
            'media' => $media,
            'media_by_role' => $this->mediaFormatter->groupByRole($media),
            'categories' => $product->categories->map(fn (TaxonomyTerm $term) => [
                'id' => $term->id,
                'name' => $term->name,
                'slug' => $term->slug,
            ])->values(),
        ];
    }

    private function termPayload(TaxonomyTerm $term): array
    {
        return [
            'id' => $term->id,
            'name' => $term->name,
            'slug' => $term->slug,
            'description' => $term->description,
            'seo' => [
                'title' => $term->seo_title,
                'description' => $term->seo_description,
            ],
            'children' => $term->children
                ->where('is_active', true)
                ->map(fn (TaxonomyTerm $child) => $this->termPayload($child))
                ->values(),
        ];
    }

    private function cacheKey(Site $site, string $scope, string $name): string
    {
        return "site:{$site->id}:{$name}:{$scope}";
    }

    private function menuLocationUsesLanguage(string $location): bool
    {
        $config = CmsSetLoader::get('menus', 'menu') ?? [];
        $locations = $config['languageLocations'] ?? [];

        if (array_key_exists($location, $locations)) {
            return (bool) $locations[$location];
        }

        return (bool) ($config['hasLanguage'] ?? $config['languageEnabled'] ?? false);
    }
}
