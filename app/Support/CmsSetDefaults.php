<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CmsSetDefaults
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalize(array $config, string $file): array
    {
        $explicit = $config;
        $filenameKey = self::keyFromFilename($file);
        $module = $config['module'] ?? $filenameKey;
        $pageType = $config['pageType'] ?? self::defaultPageType($filenameKey, $config);
        $strategy = $config['strategy'] ?? self::defaultStrategy($module, $pageType);
        $common = require config_path('cms/set_common.php');

        $config['pageType'] = $pageType;
        $config['module'] = $module;
        $config['menuValue'] ??= $module;
        $config['strategy'] = $strategy;
        $config['moduleName'] ??= $config['label'] ?? $module;
        $config['label'] ??= $config['moduleName'];

        $tableDefaults = $common['tables'][$strategy] ?? $common['tables']['default'];
        $config = array_replace($tableDefaults, $config);

        if ($pageType === 'list') {
            $config = array_replace($common['list'], $config);
        }

        if ($pageType === 'taxonomy') {
            $config = array_replace($common['taxonomy'] ?? [], $config);
        }

        if ($pageType === 'info') {
            $config = array_replace($common['info'], $config);
            $config['hiddenFields'] = array_replace(
                ['d_class1' => $module],
                $config['hiddenFields'] ?? []
            );
        }

        foreach ($common['strategies'][$strategy] ?? [] as $key => $value) {
            if (!array_key_exists($key, $explicit)) {
                $config[$key] = $value;
            }
        }

        if (!isset($config['status_options'])) {
            $statusKey = $pageType === 'info' ? 'info' : $strategy;
            $config['status_options'] = $common['status_options'][$statusKey] ?? [];
        }

        if (!empty($config['taxonomy']) && !isset($config['category_relation'])) {
            $config['category_relation'] = $common['strategies'][$strategy]['category_relation'] ?? 'terms';
        }

        if ($pageType === 'info' && !isset($config['content_type'])) {
            $config['content_type'] = 'info_' . Str::snake(Str::beforeLast($module, 'Info'));
        }

        $config = self::normalizeLegacyStructure($config);

        if (!empty($config['taxonomy']) && !isset($config['category_relation'])) {
            $config['category_relation'] = $common['strategies'][$strategy]['category_relation'] ?? 'terms';
        }

        return self::normalizeFields($config);
    }

    private static function defaultStrategy(string $module, string $pageType): string
    {
        if ($pageType === 'info') {
            return 'content';
        }

        if ($pageType === 'taxonomy') {
            return 'taxonomy';
        }

        return match ($module) {
            'product', 'products' => 'product',
            'contact', 'contactus' => 'contact',
            default => 'content',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function defaultPageType(string $filenameKey, array $config): string
    {
        if (Str::endsWith($filenameKey, 'Info')) {
            return 'info';
        }

        if (($config['strategy'] ?? null) === 'taxonomy' || ($config['tableName'] ?? null) === 'taxonomies') {
            return 'taxonomy';
        }

        return 'list';
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeFields(array $config): array
    {
        foreach (['form_sections', 'sections'] as $sectionKey) {
            foreach ($config[$sectionKey] ?? [] as $sectionName => $section) {
                foreach ($section['fields'] ?? [] as $index => $field) {
                    $field = self::normalizeField($field, $config);

                    if (
                        ($field['type'] ?? null) === 'select'
                        && empty($field['options'])
                        && in_array(($field['maps_to'] ?? $field['name'] ?? null), ['status', 'is_active'], true)
                    ) {
                        $field['options'] = $config['status_options'] ?? [];
                    }

                    Arr::set($config, "{$sectionKey}.{$sectionName}.fields.{$index}", $field);
                }
            }
        }

        return $config;
    }

    /**
     * Accepts the old CMS set structure and converts it to the internal Laravel
     * shape used by controllers and Blade partials.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeLegacyStructure(array $config): array
    {
        $config['label'] ??= $config['moduleName'] ?? null;
        $config['moduleName'] ??= $config['label'] ?? null;

        if (!isset($config['list_columns']) && isset($config['listPage']['columns']) && is_array($config['listPage']['columns'])) {
            $config['list_columns'] = collect($config['listPage']['columns'])
                ->map(fn (array $column) => self::normalizeListColumn($column, $config))
                ->filter()
                ->values()
                ->all();
        }

        if (!isset($config['list_image_type']) && isset($config['listPage']['imageFileType'])) {
            $config['list_image_type'] = $config['listPage']['imageFileType'];
        }

        if (!isset($config['taxonomy']) && isset($config['listPage']['categoryName'])) {
            $config['taxonomy'] = $config['listPage']['categoryName'];
        }

        if (isset($config['listPage']['categoryField']) && !isset($config['taxonomy_hierarchical'])) {
            $config['taxonomy_hierarchical'] = is_array($config['listPage']['categoryField']);
        }

        if (!isset($config['form_sections']) && isset($config['detailPage']) && in_array(($config['pageType'] ?? null), ['list', 'taxonomy'], true)) {
            $config['form_sections'] = self::normalizeSections($config['detailPage']);
        }

        if (!isset($config['sections']) && isset($config['detailPage']) && ($config['pageType'] ?? null) === 'info') {
            $config['sections'] = self::normalizeSections($config['detailPage']);
        }

        return $config;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $sections
     * @return array<string, array<string, mixed>>
     */
    private static function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach (array_values($sections) as $index => $section) {
            $key = $section['key'] ?? Str::slug((string) ($section['sheetTitle'] ?? $section['label'] ?? "section-{$index}")) ?: "section_{$index}";
            $normalized[$key] = [
                'label' => $section['label'] ?? $section['sheetTitle'] ?? "區塊 {$index}",
                'legacy_sheet' => $section['legacy_sheet'] ?? $section['sheetTitle'] ?? null,
                'boxTitle' => $section['boxTitle'] ?? null,
                'fields' => $section['fields'] ?? $section['items'] ?? [],
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private static function normalizeListColumn(array $column, array $config): ?array
    {
        $field = $column['field'] ?? $column['key'] ?? null;
        $type = $column['type'] ?? 'text';

        if (in_array($field, ['edit', 'delete', 'view'], true)) {
            return null;
        }

        if ($field === 'next_level') {
            return null;
        }

        $column['legacy_field'] ??= $field;
        $column['key'] ??= self::internalFieldName($field, $config, $type, true);

        if ($type === 'active') {
            $column['type'] = 'status';
        } elseif ($field === 'pin') {
            $column['type'] = 'pin';
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeField(array $field, array $config): array
    {
        if (isset($field['field']) && !isset($field['legacy_field'])) {
            $field['legacy_field'] = $field['field'];
        }

        if (!isset($field['name'])) {
            $field['name'] = self::internalFieldName($field['legacy_field'] ?? null, $config, $field['type'] ?? null, false);
        }

        if (!isset($field['legacy_field']) && isset($field['name'])) {
            $field['legacy_field'] = $field['name'];
        }

        foreach ([
            'checkDuplicate' => 'check_duplicate',
            'useTiny' => 'use_tiny',
            'hasGallery' => 'has_gallery',
            'fileType' => 'file_type',
        ] as $legacyKey => $modernKey) {
            if (array_key_exists($legacyKey, $field) && !array_key_exists($modernKey, $field)) {
                $field[$modernKey] = $field[$legacyKey];
            }
        }

        $legacyField = $field['legacy_field'] ?? null;
        if (($field['type'] ?? null) === 'select' && !empty($field['category']) && !(($config['pageType'] ?? null) === 'taxonomy' && $legacyField === 'parent_id')) {
            $field['type'] = !empty($field['linked']) || is_array($legacyField) ? 'linked_taxonomy' : 'taxonomy';
            $field['name'] = self::taxonomyInputName($legacyField);
            $field['taxonomy_code'] = self::taxonomyCode($field['category']);
        }

        if (($field['maps_to'] ?? null) === null && ($field['name'] ?? null) !== ($legacyField ?? null)) {
            $mapsTo = self::mapsToField($legacyField, $config);
            if ($mapsTo && $mapsTo !== $field['name']) {
                $field['maps_to'] = $mapsTo;
            }
        }

        if (!empty($field['fields']) && is_array($field['fields'])) {
            $field['fields'] = array_map(fn (array $subField) => self::normalizeNestedField($subField), $field['fields']);
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<string, mixed>
     */
    private static function normalizeNestedField(array $field): array
    {
        if (isset($field['field']) && !isset($field['name'])) {
            $field['name'] = $field['field'];
        }

        if (isset($field['fileType']) && !isset($field['file_type'])) {
            $field['file_type'] = $field['fileType'];
        }

        return $field;
    }

    private static function internalFieldName(mixed $legacyField, array $config, ?string $type, bool $forList): string
    {
        if (is_array($legacyField)) {
            return 'term_ids';
        }

        $legacyField = (string) ($legacyField ?? '');

        if ($legacyField === '') {
            return $forList ? 'value' : 'field';
        }

        if ($forList) {
            return match ($legacyField) {
                'd_sort', 't_sort', 'm_sort' => 'sort_order',
                'pin' => 'is_pinned',
                'd_date' => 'published_at',
                'm_date' => 'created_at',
                'd_title', 'm_title' => ($config['strategy'] ?? null) === 'product' ? 'name' : 'title',
                't_name' => 'name',
                'd_view' => 'view_count',
                'image' => 'image',
                'd_active', 't_active', 'm_active' => 'status',
                'd_class2', 't_id' => 'categories',
                'read' => 'is_read',
                default => $legacyField,
            };
        }

        if ($type === 'select' && str_starts_with($legacyField, 'd_class')) {
            return 'term_ids';
        }

        return self::mapsToField($legacyField, $config) ?? $legacyField;
    }

    private static function taxonomyInputName(mixed $legacyField): string
    {
        if (is_array($legacyField)) {
            return 'term_ids';
        }

        $legacyField = (string) ($legacyField ?? '');

        if ($legacyField === '' || preg_match('/^[dt]_class\d+$/', $legacyField)) {
            return 'term_ids';
        }

        return 'term_ids_' . Str::of($legacyField)
            ->replace(['[', ']', '-', '.'], '_')
            ->snake()
            ->trim('_')
            ->toString();
    }

    private static function taxonomyCode(mixed $category): string
    {
        if (is_array($category)) {
            return (string) end($category);
        }

        return (string) $category;
    }

    private static function mapsToField(mixed $legacyField, array $config): ?string
    {
        if (is_array($legacyField)) {
            return 'term_ids';
        }

        return match ((string) ($legacyField ?? '')) {
            'd_class2', 'd_class3', 'd_class4', 'd_class5' => 'term_ids',
            'd_title' => ($config['strategy'] ?? null) === 'product' ? 'name' : 'title',
            'd_content' => ($config['strategy'] ?? null) === 'product' ? 'description' : 'body',
            'd_date' => 'published_at',
            'd_update_time' => 'updated_at',
            'd_active' => 'status',
            'd_slug' => 'slug',
            'd_seo_title' => 'seo_title',
            'd_description' => 'seo_description',
            't_name' => 'name',
            't_slug' => 'slug',
            't_seo_title' => 'seo_title',
            't_description' => 'seo_description',
            't_active' => 'is_active',
            'm_inquiry' => 'inquiry',
            'm_title' => 'subject',
            'm_name' => 'name',
            'm_email' => 'email',
            'm_phone' => 'phone',
            'm_address' => 'address',
            'm_data1' => 'preferred_contact_time',
            'm_data2' => 'contact_date',
            'm_content' => 'content',
            'm_date' => 'created_at',
            default => null,
        };
    }

    private static function keyFromFilename(string $file): string
    {
        return Str::beforeLast(basename($file), 'Set.php');
    }
}
