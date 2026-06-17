<?php

use App\Models\Language;

return [
    'adminKey' => 'languageType',
    'module' => 'languageType',
    'moduleName' => '語系管理',
    'model' => Language::class,
    'strategy' => 'language',
    'hasLanguage' => false,
    'hasTrash' => false,
    'sort_column' => 'sort_order',

    'list_columns' => [
        ['key' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => 74],
        ['key' => 'name', 'label' => '語系名稱', 'type' => 'text', 'width' => 180],
        ['key' => 'name_en', 'label' => '英文名稱', 'type' => 'text', 'width' => 180],
        ['key' => 'slug', 'label' => '代碼', 'type' => 'text', 'width' => 100],
        ['key' => 'locale', 'label' => 'Locale', 'type' => 'text', 'width' => 140],
        ['key' => 'is_default', 'label' => '預設', 'type' => 'boolean', 'width' => 60],
        ['key' => 'is_active', 'label' => '狀態', 'type' => 'boolean_status', 'width' => 70],
    ],

    'form_sections' => [
        'basic' => [
            'label' => '資料設定',
            'fields' => [
                ['name' => 'name', 'label' => '語系名稱', 'type' => 'text', 'required' => true],
                ['name' => 'name_en', 'label' => '英文名稱', 'type' => 'text'],
                ['name' => 'slug', 'label' => '語系代碼', 'type' => 'text', 'required' => true, 'note' => '例如：tw、en、jp'],
                ['name' => 'locale', 'label' => 'Locale', 'type' => 'text', 'required' => true, 'note' => '例如：zh-Hant-TW、en、ja'],
                ['name' => 'is_default', 'label' => '預設語系', 'type' => 'checkbox'],
                ['name' => 'is_active', 'label' => '狀態', 'type' => 'select', 'options' => [1 => '顯示', 0 => '不顯示']],
            ],
        ],
    ],
];
