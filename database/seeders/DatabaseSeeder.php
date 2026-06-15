<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\CmsMenu;
use App\Models\ContactMessage;
use App\Models\Content;
use App\Models\ContentTranslation;
use App\Models\ContentType;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::transaction(function (): void {
            $tenant = Tenant::query()->updateOrCreate(
                ['slug' => 'goods-design'],
                ['name' => 'Goods Design', 'status' => 'active', 'plan_code' => 'internal']
            );

            $site = Site::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => 'main-site'],
                [
                    'name' => '主網站',
                    'status' => 'active',
                    'default_locale' => 'zh-Hant-TW',
                    'timezone' => 'Asia/Taipei',
                    'currency_code' => 'TWD',
                ]
            );

            SiteDomain::query()->updateOrCreate(
                ['domain' => 'localhost:8013'],
                ['site_id' => $site->id, 'is_primary' => true, 'force_https' => false]
            );

            foreach ([
                ['code' => 'home', 'name' => '首頁'],
                ['code' => 'info_keywords', 'name' => '全站設定'],
                ['code' => 'info_pop', 'name' => '燈箱設定'],
                ['code' => 'page', 'name' => '一般內容'],
                ['code' => 'news', 'name' => '最新消息'],
                ['code' => 'blog', 'name' => '部落格文章'],
                ['code' => 'product_landing', 'name' => '產品介紹頁'],
            ] as $type) {
                ContentType::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $type['code']],
                    ['name' => $type['name'], 'is_active' => true, 'config' => []]
                );
            }

            foreach ([
                'newsCate' => ['name' => '最新消息分類', 'hierarchical' => false, 'terms' => ['公司消息', '活動公告']],
                'newsTag' => ['name' => '最新消息標籤', 'hierarchical' => false, 'terms' => ['重要', '活動']],
                'productCate' => ['name' => '產品分類', 'hierarchical' => true, 'terms' => ['主要商品', '精選商品']],
                'productTag' => ['name' => '產品標籤', 'hierarchical' => false, 'terms' => ['新品', '熱銷']],
            ] as $code => $taxonomyConfig) {
                $taxonomy = Taxonomy::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $code],
                    ['name' => $taxonomyConfig['name'], 'is_hierarchical' => $taxonomyConfig['hierarchical']]
                );

                foreach ($taxonomyConfig['terms'] as $index => $termName) {
                    TaxonomyTerm::query()->updateOrCreate(
                        [
                            'taxonomy_id' => $taxonomy->id,
                            'locale' => $site->default_locale,
                            'slug' => Str::slug($termName) ?: 'term-' . ($index + 1),
                        ],
                        ['name' => $termName, 'sort_order' => $index + 1, 'is_active' => true]
                    );
                }
            }

            if (Schema::hasTable('cms_menus')) {
                $homeTypeId = ContentType::query()
                    ->where('site_id', $site->id)
                    ->where('code', 'home')
                    ->value('id');

                foreach ([
                    ['title' => 'Dashboard', 'module_key' => 'dashboard', 'route_name' => 'admin.dashboard', 'icon' => 'bx bx-home-alt'],
                    ['title' => '圖片庫', 'module_key' => 'media-library', 'route_name' => 'admin.media-library.index', 'icon' => 'bx bx-images'],
                    ['title' => '首頁', 'module_key' => 'home', 'route_name' => null, 'url' => '#', 'icon' => 'bx bx-file'],
                    ['title' => '最新消息', 'module_key' => 'news', 'route_name' => 'admin.news.index', 'icon' => 'bx bx-file'],
                    ['title' => '產品', 'module_key' => 'products', 'route_name' => 'admin.products.index', 'icon' => 'bx bx-file'],
                    ['title' => '聯絡我們', 'module_key' => 'contact', 'route_name' => 'admin.contact.index', 'icon' => 'bx bx-detail'],
                    ['title' => '全站設定', 'module_key' => 'settings', 'route_name' => 'admin.info.edit', 'icon' => 'bx bx-cog', 'settings' => ['route_params' => ['module' => 'keywordsInfo']]],
                    ['title' => '權限管理', 'module_key' => 'permissions', 'url' => '#', 'icon' => 'bx bx-user-circle'],
                    ['title' => '選單管理', 'module_key' => 'menus', 'route_name' => 'admin.menus.index', 'icon' => 'fa-solid fa-bars'],
                ] as $index => $menu) {
                    CmsMenu::query()->updateOrCreate(
                        [
                            'site_id' => $site->id,
                            'location' => 'backend',
                            'parent_id' => null,
                            'module_key' => $menu['module_key'],
                        ],
                        [
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

                $childMenus = [
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

                foreach ($childMenus as $parentModule => $children) {
                    $parentMenu = CmsMenu::query()
                        ->where('site_id', $site->id)
                        ->where('location', 'backend')
                        ->whereNull('parent_id')
                        ->where('module_key', $parentModule)
                        ->first();

                    if (!$parentMenu) {
                        continue;
                    }

                    foreach ($children as $index => $childMenu) {
                        CmsMenu::query()->updateOrCreate(
                            [
                                'site_id' => $site->id,
                                'location' => 'backend',
                                'module_key' => $childMenu['module_key'],
                            ],
                            [
                                'parent_id' => $parentMenu->id,
                                'title' => $childMenu['title'],
                                'type' => !empty($childMenu['route_name']) ? 'route' : 'custom',
                                'url' => $childMenu['url'] ?? null,
                                'route_name' => $childMenu['route_name'] ?? null,
                                'icon' => null,
                                'target' => '_self',
                                'is_active' => true,
                                'sort_order' => $index + 1,
                                'settings' => $childMenu['settings'] ?? null,
                            ]
                        );
                    }
                }
            }

            $admin = AdminUser::query()->updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => '系統管理員',
                    'password' => Hash::make('password'),
                    'status' => 'active',
                ]
            );

            $roleId = DB::table('roles')->where('code', 'super_admin')->value('id');
            if (!$roleId) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'Super Admin',
                    'code' => 'super_admin',
                    'scope' => 'platform',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('admin_site_roles')->updateOrInsert(
                [
                    'admin_user_id' => $admin->id,
                    'tenant_id' => $tenant->id,
                    'site_id' => $site->id,
                    'role_id' => $roleId,
                ],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $homeType = ContentType::query()->where('site_id', $site->id)->where('code', 'home')->first()
                ?? ContentType::query()->where('site_id', $site->id)->where('code', 'page')->first();

            if ($homeType) {
                $existingHomeTranslation = ContentTranslation::query()
                    ->where('locale', $site->default_locale)
                    ->where('slug', 'home')
                    ->whereHas('content', fn ($query) => $query->where('site_id', $site->id))
                    ->first();

                $content = $existingHomeTranslation?->content ?? Content::query()->firstOrCreate(
                    ['site_id' => $site->id, 'content_type_id' => $homeType->id, 'sort_order' => 1],
                    ['status' => 'published', 'published_at' => now()]
                );

                $content->update([
                    'content_type_id' => $homeType->id,
                    'status' => 'published',
                    'published_at' => $content->published_at ?? now(),
                ]);

                $translationData = [
                    'title' => '首頁',
                    'summary' => 'Laravel 後台 CMS 首頁顯示。',
                    'body' => '這筆內容由 Laravel seed 建立，可在後台內容管理中維護。',
                    'seo_title' => '首頁',
                    'seo_description' => 'Laravel 後台 CMS 首頁。',
                ];

                if ($existingHomeTranslation) {
                    $existingHomeTranslation->update($translationData);
                } else {
                    $content->translations()->create([
                        'locale' => $site->default_locale,
                        'slug' => 'home',
                        ...$translationData,
                    ]);
                }
            }

            if (class_exists(ContactMessage::class) && Schema::hasTable('contact_messages')) {
                ContactMessage::query()->updateOrCreate(
                    ['site_id' => $site->id, 'type' => 'contact', 'email' => 'customer@example.com'],
                    [
                        'subject' => '網站表單詢問',
                        'name' => '測試客戶',
                        'phone' => '0912-345-678',
                        'content' => '這是一筆聯絡我們測試資料。',
                        'status' => 'pending',
                        'is_read' => false,
                    ]
                );
            }
        });
    }
}
