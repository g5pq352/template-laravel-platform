<?php

$module = 'newsCate';
$hasHierarchy = false;

return [
    'module' => $module,
    'moduleName' => '最新消息分類',
    'strategy' => 'taxonomy',
    'pageType' => 'taxonomy',
    'taxonomy' => $module,
    'taxonomy_label' => '最新消息分類',
    'taxonomy_hierarchical' => $hasHierarchy,
    'tableName' => 'taxonomies',
    'primaryKey' => 't_id',

    'listPage' => [
        'title' => '分類列表',
        'hasHierarchy' => $hasHierarchy,
        'columns' => [
            ['field' => 'sort_order', 'label' => '排序', 'type' => 'sort', 'width' => 74],
            ['field' => 'created_at', 'label' => '建立日期', 'type' => 'date', 'width' => 142],
            ['field' => 't_name', 'label' => '分類名稱', 'type' => 'text', 'width' => 400],
            ['field' => 't_active', 'label' => '狀態', 'type' => 'active', 'width' => 60],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => 30],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => 30],
        ],
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                ['type' => 'text', 'field' => 't_name', 'label' => '分類名稱', 'required' => true, 'checkDuplicate' => true],
                ['type' => 'textarea', 'field' => 'description', 'label' => '描述', 'rows' => 5],
                ['type' => 'select', 'field' => 't_active', 'label' => '顯示狀態'],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                ['type' => 'text', 'field' => 't_slug', 'label' => '網址別名 (slug)', 'note' => '用於網址列，留空則自動從標題產生'],
                ['type' => 'text', 'field' => 't_seo_title', 'label' => 'SEO 標題 (Meta Title)', 'note' => '建議長度：50-60 字元'],
                ['type' => 'textarea', 'field' => 't_description', 'label' => 'SEO 描述 (Meta Description)', 'rows' => 4, 'note' => '建議長度：150-160 字元'],
            ],
        ],
    ],
];
