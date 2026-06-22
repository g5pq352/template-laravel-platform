<?php

namespace App\Support;

use App\Models\CmsMenu;
use App\Models\ContentType;
use App\Models\Language;
use App\Models\Site;
use App\Models\Taxonomy;
use Illuminate\Support\Facades\Schema;

class SiteBootstrapper
{
    public function bootstrap(Site $site): void
    {
        $this->bootstrapLanguages($site);
        $this->bootstrapContentTypes($site);
        $this->bootstrapTaxonomies($site);
        $this->bootstrapBackendMenus($site);
    }

    private function bootstrapLanguages(Site $site): void
    {
        if (!Schema::hasTable('languages')) {
            return;
        }

        foreach ($this->defaultLanguages($site) as $language) {
            Language::query()->updateOrCreate(
                ['site_id' => $site->id, 'slug' => $language['slug']],
                [
                    'name' => $language['name'],
                    'name_en' => $language['name_en'],
                    'locale' => $language['locale'],
                    'is_default' => $language['is_default'],
                    'is_active' => true,
                    'sort_order' => $language['sort_order'],
                ]
            );
        }
    }

    private function bootstrapContentTypes(Site $site): void
    {
        if (!Schema::hasTable('content_types')) {
            return;
        }

        foreach ($this->defaultContentTypes() as $type) {
            ContentType::query()->updateOrCreate(
                ['site_id' => $site->id, 'code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true, 'config' => []]
            );
        }
    }

    private function bootstrapTaxonomies(Site $site): void
    {
        if (!Schema::hasTable('taxonomies')) {
            return;
        }

        foreach ($this->defaultTaxonomies() as $taxonomy) {
            Taxonomy::query()->updateOrCreate(
                ['site_id' => $site->id, 'code' => $taxonomy['code']],
                [
                    'name' => $taxonomy['name'],
                    'is_hierarchical' => $taxonomy['is_hierarchical'],
                ]
            );
        }
    }

    private function bootstrapBackendMenus(Site $site): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        $locale = $site->default_locale ?: 'zh-Hant-TW';
        $parents = [];

        foreach ($this->defaultBackendMenus($site) as $index => $menu) {
            $parents[$menu['module_key']] = CmsMenu::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'location' => 'backend',
                    'parent_id' => null,
                    'module_key' => $menu['module_key'],
                ],
                [
                    'locale' => $locale,
                    'title' => $menu['title'],
                    'type' => !empty($menu['route_name']) ? 'route' : 'custom',
                    'url' => $menu['url'] ?? null,
                    'route_name' => $menu['route_name'] ?? null,
                    'icon' => $menu['icon'],
                    'target' => '_self',
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'settings' => $menu['settings'] ?? null,
                ]
            );
        }

        foreach ($this->defaultBackendChildMenus() as $parentModule => $children) {
            $parent = $parents[$parentModule] ?? null;
            if (!$parent) {
                continue;
            }

            foreach ($children as $index => $menu) {
                CmsMenu::query()->updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'location' => 'backend',
                        'module_key' => $menu['module_key'],
                    ],
                    [
                        'parent_id' => $parent->id,
                        'locale' => $locale,
                        'title' => $menu['title'],
                        'type' => !empty($menu['route_name']) ? 'route' : 'custom',
                        'url' => $menu['url'] ?? null,
                        'route_name' => $menu['route_name'] ?? null,
                        'icon' => null,
                        'target' => '_self',
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'settings' => $menu['settings'] ?? null,
                    ]
                );
            }
        }
    }

    private function defaultLanguages(Site $site): array
    {
        return [
            ['slug' => 'tw', 'name' => '繁體中文', 'name_en' => 'Traditional Chinese', 'locale' => $site->default_locale ?: 'zh-Hant-TW', 'is_default' => true, 'sort_order' => 1],
            ['slug' => 'en', 'name' => 'English', 'name_en' => 'English', 'locale' => 'en', 'is_default' => false, 'sort_order' => 2],
            ['slug' => 'cn', 'name' => '簡體中文', 'name_en' => 'Simplified Chinese', 'locale' => 'zh-Hans-CN', 'is_default' => false, 'sort_order' => 3],
            ['slug' => 'jp', 'name' => '日文', 'name_en' => 'Japanese', 'locale' => 'ja', 'is_default' => false, 'sort_order' => 4],
        ];
    }

    private function defaultContentTypes(): array
    {
        return [
            ['code' => 'home', 'name' => '首頁'],
            ['code' => 'info_keywords', 'name' => '全站設定'],
            ['code' => 'info_pop', 'name' => '燈箱設定'],
            ['code' => 'page', 'name' => '一般內容'],
            ['code' => 'news', 'name' => '最新消息'],
            ['code' => 'blog', 'name' => '部落格文章'],
            ['code' => 'product_landing', 'name' => '產品介紹頁'],
        ];
    }

    private function defaultTaxonomies(): array
    {
        return [
            ['code' => 'newsCate', 'name' => '最新消息分類', 'is_hierarchical' => false],
            ['code' => 'newsTag', 'name' => '最新消息標籤', 'is_hierarchical' => false],
            ['code' => 'productCate', 'name' => '產品分類', 'is_hierarchical' => true],
            ['code' => 'productTag', 'name' => '產品標籤', 'is_hierarchical' => false],
        ];
    }

    private function defaultBackendMenus(Site $site): array
    {
        $menus = [
            ['title' => 'Dashboard', 'module_key' => 'dashboard', 'route_name' => 'admin.dashboard', 'icon' => 'bx bx-home-alt'],
            ['title' => '圖片庫', 'module_key' => 'media-library', 'route_name' => 'admin.media-library.index', 'icon' => 'bx bx-images'],
            ['title' => '首頁', 'module_key' => 'home', 'url' => '#', 'icon' => 'bx bx-file'],
            ['title' => '最新消息', 'module_key' => 'news', 'route_name' => 'admin.news.index', 'icon' => 'bx bx-file'],
            ['title' => '產品', 'module_key' => 'products', 'route_name' => 'admin.products.index', 'icon' => 'bx bx-file'],
            ['title' => '聯絡我們', 'module_key' => 'contact', 'route_name' => 'admin.contact.index', 'icon' => 'bx bx-detail'],
            ['title' => '全站設定', 'module_key' => 'settings', 'route_name' => 'admin.info.edit', 'icon' => 'bx bx-cog', 'settings' => ['route_params' => ['module' => 'keywordsInfo']]],
            ['title' => '多站管理', 'module_key' => 'sites', 'route_name' => 'admin.sites.index', 'icon' => 'bx bx-buildings'],
            ['title' => '權限管理', 'module_key' => 'permissions', 'url' => '#', 'icon' => 'bx bx-user-circle'],
            ['title' => '選單管理', 'module_key' => 'menus', 'route_name' => 'admin.menus.index', 'icon' => 'fas fa-bars'],
        ];

        return $site->isMainSite()
            ? $menus
            : array_values(array_filter($menus, fn (array $menu) => $menu['module_key'] !== 'sites'));
    }

    private function defaultBackendChildMenus(): array
    {
        return [
            'home' => [
                ['title' => '首頁顯示', 'module_key' => 'home.content', 'route_name' => 'admin.home-display.index'],
                ['title' => '燈箱設定', 'module_key' => 'popInfo', 'route_name' => 'admin.info.edit', 'settings' => ['route_params' => ['module' => 'popInfo']]],
            ],
            'news' => [
                ['title' => '最新消息列表', 'module_key' => 'news.list', 'route_name' => 'admin.news.index'],
                ['title' => '分類', 'module_key' => 'news.categories', 'route_name' => 'admin.taxonomies.index', 'settings' => ['route_params' => ['taxonomy' => 'newsCate']]],
                ['title' => '標籤', 'module_key' => 'news.tags', 'route_name' => 'admin.taxonomies.index', 'settings' => ['route_params' => ['taxonomy' => 'newsTag']]],
            ],
            'products' => [
                ['title' => '產品列表', 'module_key' => 'products.list', 'route_name' => 'admin.products.index'],
                ['title' => '分類', 'module_key' => 'products.categories', 'route_name' => 'admin.taxonomies.index', 'settings' => ['route_params' => ['taxonomy' => 'productCate']]],
                ['title' => '標籤', 'module_key' => 'products.tags', 'route_name' => 'admin.taxonomies.index', 'settings' => ['route_params' => ['taxonomy' => 'productTag']]],
            ],
            'contact' => [
                ['title' => '聯絡我們列表', 'module_key' => 'contact.list', 'route_name' => 'admin.contact.index'],
            ],
            'menus' => [
                ['title' => '後端選單列表', 'module_key' => 'menus.backend', 'route_name' => 'admin.menus.index', 'settings' => ['query_params' => ['location' => 'backend']]],
                ['title' => '前端選單列表', 'module_key' => 'menus.frontend', 'route_name' => 'admin.menus.index', 'settings' => ['query_params' => ['location' => 'frontend']]],
                ['title' => '頁尾選單列表', 'module_key' => 'menus.footer', 'route_name' => 'admin.menus.index', 'settings' => ['query_params' => ['location' => 'footer']]],
            ],
        ];
    }
}
