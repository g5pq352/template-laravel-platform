<?php

use App\Models\LanguagePack;

return [
    'adminKey' => 'languagePack',
    'module' => 'languagePack',
    'moduleName' => '語言包管理',
    'model' => LanguagePack::class,
    'strategy' => 'language_pack',
    'hasLanguage' => false,
    'hasTrash' => false,
    'sort_column' => 'sort_order',

    'list_columns' => [
        ['key' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => 74],
        ['key' => 'key', 'label' => 'Key', 'type' => 'text', 'width' => 180],
        ['key' => 'note', 'label' => '備註', 'type' => 'text', 'width' => 220],
        ['key' => 'translations', 'label' => '翻譯內容', 'type' => 'language_pack_values'],
    ],

    'form_sections' => [
        'basic' => [
            'label' => '資料設定',
            'fields' => [
                ['name' => 'key', 'label' => '語言鍵值', 'type' => 'text', 'required' => true],
                ['name' => 'note', 'label' => '備註', 'type' => 'text'],
                ['name' => 'translations', 'label' => '翻譯內容', 'type' => 'language_pack_values'],
            ],
        ],
    ],
];
