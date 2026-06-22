<?php

namespace App\Support;

use App\Models\CmsMenu;
use App\Models\ContentType;
use App\Models\Site;
use App\Models\Taxonomy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SiteModuleManager
{
    private const STANDARD_MODULES = [
        'news' => [
            'label' => '最新消息',
            'admin_key' => 'news',
            'files' => ['newsSet.php', 'newsCateSet.php', 'newsTagSet.php'],
            'content_types' => [
                ['code' => 'news', 'name' => '最新消息'],
            ],
            'taxonomies' => [
                ['code' => 'newsCate', 'name' => '最新消息分類', 'is_hierarchical' => false],
                ['code' => 'newsTag', 'name' => '最新消息標籤', 'is_hierarchical' => false],
            ],
        ],
        'products' => [
            'label' => '產品',
            'admin_key' => 'products',
            'files' => ['productSet.php', 'productCateSet.php', 'productTagSet.php'],
            'taxonomies' => [
                ['code' => 'productCate', 'name' => '產品分類', 'is_hierarchical' => true],
                ['code' => 'productTag', 'name' => '產品標籤', 'is_hierarchical' => false],
            ],
        ],
        'contact' => [
            'label' => '聯絡我們',
            'admin_key' => 'contact',
            'files' => ['contactusSet.php'],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function standardModuleKeys(): array
    {
        return array_keys(self::STANDARD_MODULES);
    }

    /**
     * @return list<string>
     */
    public function enabledModuleKeys(Site $site): array
    {
        $modules = Arr::wrap($site->settings['enabled_modules'] ?? self::standardModuleKeys());

        return collect($modules)
            ->map(fn (mixed $module): string => trim((string) $module))
            ->map(fn (string $module): string => $this->normalizeStandardModuleKey($module))
            ->filter(fn (string $module): bool => array_key_exists($module, self::STANDARD_MODULES))
            ->unique()
            ->values()
            ->all();
    }

    public function syncDatabase(Site $site): void
    {
        $enabled = $this->enabledModuleKeys($site);

        $this->syncStandardContentTypes($site, $enabled);
        $this->syncStandardTaxonomies($site, $enabled);
        $this->syncCustomModules($site);
        $this->syncBackendModuleVisibility($site, $enabled);
        $this->syncCustomBackendMenus($site);
    }

    public function provisionSetFiles(Site $site): void
    {
        $setPath = $this->siteSetPath($site);
        if (!$setPath) {
            return;
        }

        File::ensureDirectoryExists($setPath);

        foreach ($this->enabledModuleKeys($site) as $module) {
            foreach (self::STANDARD_MODULES[$module]['files'] ?? [] as $filename) {
                $this->copySetFile($filename, $setPath);
            }
        }

        foreach ($this->customModules($site) as $module) {
            $this->writeCustomSetFiles($setPath, $module);
        }
    }

    private function syncStandardContentTypes(Site $site, array $enabled): void
    {
        if (!Schema::hasTable('content_types')) {
            return;
        }

        foreach (self::STANDARD_MODULES as $module => $definition) {
            foreach ($definition['content_types'] ?? [] as $type) {
                ContentType::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $type['code']],
                    [
                        'name' => $type['name'],
                        'is_active' => in_array($module, $enabled, true),
                        'config' => [],
                    ]
                );
            }
        }
    }

    private function syncStandardTaxonomies(Site $site, array $enabled): void
    {
        if (!Schema::hasTable('taxonomies')) {
            return;
        }

        foreach ($enabled as $module) {
            foreach (self::STANDARD_MODULES[$module]['taxonomies'] ?? [] as $taxonomy) {
                Taxonomy::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $taxonomy['code']],
                    [
                        'name' => $taxonomy['name'],
                        'is_hierarchical' => $taxonomy['is_hierarchical'],
                    ]
                );
            }
        }
    }

    private function syncCustomModules(Site $site): void
    {
        foreach ($this->customModules($site) as $module) {
            $type = $module['type'];
            $slug = $this->moduleKey($module);
            $name = $module['name'];

            if (Schema::hasTable('content_types') && in_array($type, ['single', 'multi', 'list_only', 'info'], true)) {
                ContentType::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $type === 'info' ? 'info_' . Str::snake($slug) : $slug],
                    ['name' => $name, 'is_active' => true, 'config' => ['custom_module' => true, 'template' => $type]]
                );
            }

            if (Schema::hasTable('taxonomies') && in_array($type, ['single', 'multi'], true)) {
                Taxonomy::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $slug . 'Cate'],
                    ['name' => $name . '分類', 'is_hierarchical' => $type === 'multi']
                );
                Taxonomy::query()->updateOrCreate(
                    ['site_id' => $site->id, 'code' => $slug . 'Tag'],
                    ['name' => $name . '標籤', 'is_hierarchical' => false]
                );
            }
        }
    }

    private function syncCustomBackendMenus(Site $site): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        $locale = $site->default_locale ?: 'zh-Hant-TW';
        $modules = $this->customModules($site);
        $activeKeys = collect($modules)
            ->flatMap(fn (array $module): array => $this->customBackendMenuKeys($module))
            ->values()
            ->all();

        CmsMenu::query()
            ->where('site_id', $site->id)
            ->where('location', 'backend')
            ->where(function ($query): void {
                $query
                    ->where('settings->custom_module', true)
                    ->orWhere('module_key', 'like', 'custom.%');
            })
            ->when($activeKeys !== [], fn ($query) => $query->whereNotIn('module_key', $activeKeys))
            ->update(['is_active' => false]);

        $sortOrder = $this->nextBackendSortOrder($site);

        foreach ($modules as $module) {
            $key = $this->moduleKey($module);
            $name = $module['name'];
            $type = $module['type'];
            $route = $this->customModuleRoute($module);
            $existingParent = CmsMenu::query()
                ->where('site_id', $site->id)
                ->where('location', 'backend')
                ->whereNull('parent_id')
                ->where('module_key', $key)
                ->first();

            $parent = CmsMenu::query()->updateOrCreate(
                [
                    'site_id' => $site->id,
                    'location' => 'backend',
                    'parent_id' => null,
                    'module_key' => $key,
                ],
                [
                    'locale' => $locale,
                    'title' => $name,
                    'type' => !empty($route['route_name']) ? 'route' : 'custom',
                    'url' => $route['url'] ?? null,
                    'route_name' => $route['route_name'] ?? null,
                    'icon' => $type === 'contactus' ? 'bx bx-detail' : 'bx bx-file',
                    'target' => '_self',
                    'is_active' => true,
                    'sort_order' => $existingParent?->sort_order ?: $sortOrder++,
                    'settings' => $this->customMenuSettings($module, $route['route_params'] ?? []),
                ]
            );

            foreach ($this->customBackendChildren($module) as $index => $child) {
                CmsMenu::query()->updateOrCreate(
                    [
                        'site_id' => $site->id,
                        'location' => 'backend',
                        'module_key' => $child['module_key'],
                    ],
                    [
                        'parent_id' => $parent->id,
                        'locale' => $locale,
                        'title' => $child['title'],
                        'type' => 'route',
                        'url' => null,
                        'route_name' => $child['route_name'],
                        'icon' => null,
                        'target' => '_self',
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'settings' => $this->customMenuSettings($module, $child['route_params'] ?? []),
                    ]
                );
            }
        }
    }

    private function syncBackendModuleVisibility(Site $site, array $enabled): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        foreach (self::STANDARD_MODULES as $module => $definition) {
            CmsMenu::query()
                ->where('site_id', $site->id)
                ->where(function ($query) use ($definition): void {
                    $query->where('module_key', $definition['admin_key'])
                        ->orWhere('module_key', 'like', $definition['admin_key'] . '.%');
                })
                ->update(['is_active' => in_array($module, $enabled, true)]);
        }
    }

    /**
     * @return list<array{name: string, slug: string, type: string}>
     */
    private function customModules(Site $site): array
    {
        return collect(Arr::wrap($site->settings['custom_modules'] ?? []))
            ->map(fn (mixed $module): array => is_array($module) ? $module : [])
            ->map(fn (array $module): array => [
                'name' => trim((string) ($module['name'] ?? '')),
                'slug' => trim((string) ($module['slug'] ?? '')),
                'type' => trim((string) ($module['type'] ?? 'single')) ?: 'single',
            ])
            ->filter(fn (array $module): bool => $module['name'] !== '' && $module['slug'] !== '')
            ->values()
            ->all();
    }

    private function siteSetPath(Site $site): ?string
    {
        $path = trim((string) ($site->settings['cms_set_path'] ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return rtrim($path, "\\/");
        }

        return rtrim(base_path($path), "\\/");
    }

    private function copySetFile(string $filename, string $setPath): void
    {
        $source = config_path('cms/set/' . $filename);
        $target = $setPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_file($source) || is_file($target)) {
            return;
        }

        File::copy($source, $target);
    }

    private function writeCustomSetFiles(string $setPath, array $module): void
    {
        $slug = $this->moduleKey($module);
        $name = $module['name'];
        $type = $module['type'];

        $files = match ($type) {
            'info' => [$slug . 'InfoSet.php' => $this->infoSetTemplate($slug, $name)],
            'contactus' => [$slug . 'Set.php' => $this->contactSetTemplate($slug, $name)],
            default => array_filter([
                $slug . 'Set.php' => $this->contentSetTemplate($slug, $name, $type),
                $type !== 'list_only' ? $slug . 'CateSet.php' : null => $type !== 'list_only' ? $this->taxonomySetTemplate($slug . 'Cate', $name . '分類', $type === 'multi') : null,
                $type !== 'list_only' ? $slug . 'TagSet.php' : null => $type !== 'list_only' ? $this->taxonomySetTemplate($slug . 'Tag', $name . '標籤', false) : null,
            ]),
        };

        foreach ($files as $filename => $contents) {
            if (!$filename || is_file($setPath . DIRECTORY_SEPARATOR . $filename)) {
                continue;
            }

            File::put($setPath . DIRECTORY_SEPARATOR . $filename, $contents);
        }
    }

    private function contentSetTemplate(string $slug, string $name, string $type): string
    {
        $category = $slug . 'Cate';
        $tag = $slug . 'Tag';
        $hasCategory = $type !== 'list_only';
        $hierarchical = $type === 'multi';
        $categoryField = $hierarchical ? "['d_class2', 'd_class3', 'd_class4', 'd_class5']" : "'d_class2'";

        return <<<PHP
<?php

use App\Models\Content;

\$module = '{$slug}';
\$category = '{$category}';
\$hasHierarchy = {$this->bool($hierarchical)};

return [
    'module' => \$module,
    'moduleName' => '{$name}管理',
    'model' => Content::class,
    'strategy' => 'content',
    'hasLanguage' => true,
    'content_type' => '{$slug}',

    'listPage' => [
        'categoryName' => {$this->export($hasCategory ? $category : null)},
        'categoryField' => {$categoryField},
        'hasCategory' => {$this->bool($hasCategory)},
        'columns' => [
            ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => 74],
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => 142],
            ['field' => 'd_title', 'label' => '標題', 'type' => 'title', 'width' => 470],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => 60],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => 30],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => 30],
        ],
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                {$this->categoryFieldTemplate($hasCategory, $categoryField, $category, $hierarchical)}
                {$this->tagFieldTemplate($hasCategory, $tag)}
                ['type' => 'text', 'field' => 'd_title', 'label' => '標題', 'required' => true, 'checkDuplicate' => true],
                ['type' => 'editor', 'field' => 'd_content', 'label' => '內容', 'useTiny' => true, 'hasGallery' => true],
                ['type' => 'datetime', 'field' => 'd_date', 'label' => '日期'],
                ['type' => 'select', 'field' => 'd_active', 'label' => '在網頁顯示'],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_slug', 'label' => '網址別名 (slug)'],
                ['type' => 'text', 'field' => 'd_seo_title', 'label' => 'SEO 標題'],
                ['type' => 'textarea', 'field' => 'd_description', 'label' => 'SEO 描述', 'rows' => 4],
            ],
        ],
    ],
];
PHP;
    }

    private function taxonomySetTemplate(string $slug, string $name, bool $hierarchical): string
    {
        return <<<PHP
<?php

\$module = '{$slug}';
\$hasHierarchy = {$this->bool($hierarchical)};

return [
    'module' => \$module,
    'moduleName' => '{$name}',
    'strategy' => 'taxonomy',
    'hasLanguage' => true,
    'pageType' => 'taxonomy',
    'taxonomy' => \$module,
    'taxonomy_label' => '{$name}',
    'taxonomy_hierarchical' => \$hasHierarchy,
    'tableName' => 'taxonomies',
    'primaryKey' => 't_id',

    'listPage' => [
        'title' => '列表',
        'hasHierarchy' => \$hasHierarchy,
        'columns' => [
            ['field' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => 74],
            ['field' => 'created_at', 'label' => '建立日期', 'type' => 'date', 'width' => 142],
            ['field' => 't_name', 'label' => '名稱', 'type' => 'text', 'width' => 400],
            ['field' => 't_active', 'label' => '狀態', 'type' => 'active', 'width' => 60],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => 30],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => 30],
        ],
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                ['type' => 'text', 'field' => 't_name', 'label' => '名稱', 'required' => true, 'checkDuplicate' => true],
                ['type' => 'textarea', 'field' => 'description', 'label' => '描述', 'rows' => 5],
                ['type' => 'select', 'field' => 't_active', 'label' => '顯示狀態'],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                ['type' => 'text', 'field' => 't_slug', 'label' => '網址別名 (slug)'],
                ['type' => 'text', 'field' => 't_seo_title', 'label' => 'SEO 標題'],
                ['type' => 'textarea', 'field' => 't_description', 'label' => 'SEO 描述', 'rows' => 4],
            ],
        ],
    ],
];
PHP;
    }

    private function infoSetTemplate(string $slug, string $name): string
    {
        return <<<PHP
<?php

return [
    'module' => '{$slug}Info',
    'moduleName' => '{$name}',
    'hasLanguage' => true,

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_title', 'label' => '標題', 'required' => true],
                ['type' => 'textarea', 'field' => 'd_content', 'label' => '內容', 'rows' => 6],
                ['type' => 'select', 'field' => 'd_active', 'label' => '在網頁顯示'],
            ],
        ],
    ],
];
PHP;
    }

    private function contactSetTemplate(string $slug, string $name): string
    {
        return <<<PHP
<?php

use App\Models\ContactMessage;

return [
    'adminKey' => '{$slug}',
    'module' => '{$slug}',
    'moduleName' => '{$name}管理',
    'model' => ContactMessage::class,
    'strategy' => 'contact',
    'hasLanguage' => true,
    'show_add_button' => false,
    'readonly' => true,
];
PHP;
    }

    private function categoryFieldTemplate(bool $enabled, string $categoryField, string $category, bool $hierarchical): string
    {
        if (!$enabled) {
            return '';
        }

        $label = $hierarchical ? '層級分類' : '分類';

        return "['type' => 'select', 'field' => {$categoryField}, 'label' => '{$label}', 'category' => '{$category}', 'linked' => " . $this->bool($hierarchical) . "],\n                ";
    }

    private function tagFieldTemplate(bool $enabled, string $tag): string
    {
        if (!$enabled) {
            return '';
        }

        return "['type' => 'select', 'field' => 'd_tag', 'label' => '標籤', 'category' => '{$tag}', 'multiple' => true],\n                ";
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function export(mixed $value): string
    {
        return var_export($value, true);
    }

    private function moduleKey(array $module): string
    {
        return Str::camel($module['slug']);
    }

    /**
     * @return list<string>
     */
    private function customBackendMenuKeys(array $module): array
    {
        $key = $this->moduleKey($module);
        $keys = [$key];

        foreach ($this->customBackendChildren($module) as $child) {
            $keys[] = $child['module_key'];
        }

        return $keys;
    }

    /**
     * @return array{route_name?: string, route_params?: array<string, string>, url?: string|null}
     */
    private function customModuleRoute(array $module): array
    {
        $key = $this->moduleKey($module);

        if ($module['type'] === 'info') {
            return [
                'route_name' => 'admin.info.edit',
                'route_params' => ['module' => $key . 'Info'],
            ];
        }

        return ['route_name' => "admin.{$key}.index"];
    }

    /**
     * @return list<array{title: string, module_key: string, route_name: string, route_params?: array<string, string>}>
     */
    private function customBackendChildren(array $module): array
    {
        $key = $this->moduleKey($module);

        if (!in_array($module['type'], ['single', 'multi'], true)) {
            return [];
        }

        return [
            ['title' => $module['name'] . '列表', 'module_key' => $key . '.list', 'route_name' => "admin.{$key}.index"],
            ['title' => '分類', 'module_key' => $key . '.categories', 'route_name' => 'admin.taxonomies.index', 'route_params' => ['taxonomy' => $key . 'Cate']],
            ['title' => '標籤', 'module_key' => $key . '.tags', 'route_name' => 'admin.taxonomies.index', 'route_params' => ['taxonomy' => $key . 'Tag']],
        ];
    }

    /**
     * @param array<string, string> $routeParams
     * @return array<string, mixed>
     */
    private function customMenuSettings(array $module, array $routeParams = []): array
    {
        $settings = [
            'custom_module' => true,
            'custom_module_type' => $module['type'],
            'custom_module_slug' => $module['slug'],
        ];

        if ($routeParams !== []) {
            $settings['route_params'] = $routeParams;
        }

        return $settings;
    }

    private function nextBackendSortOrder(Site $site): int
    {
        $maxSort = (int) CmsMenu::query()
            ->where('site_id', $site->id)
            ->where('location', 'backend')
            ->whereNull('parent_id')
            ->max('sort_order');

        return $maxSort + 1;
    }

    private function normalizeStandardModuleKey(string $module): string
    {
        return match ($module) {
            'product' => 'products',
            'contactus' => 'contact',
            default => $module,
        };
    }
}
