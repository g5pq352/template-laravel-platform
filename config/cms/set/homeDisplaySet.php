<?php

$module = 'homeDisplay';

return [
    'module' => $module,
    'moduleName' => '首頁顯示管理',
    'label' => '首頁顯示管理',
    'pageType' => 'home_display',
    'hasLanguage' => true,

    // 可依需求切換首頁顯示來源。
    // targetContentType 對應 content_types.code。
    // targetResource 對應後台 Resource route，例如 admin.news.edit。
    'targetModule' => 'news',
    'targetResource' => 'news',
    'targetContentType' => 'news',
    'targetLabel' => '最新消息',

    'showAddButton' => false,
    'showBatchActions' => false,
    'hasTrash' => false,
    'listPage' => [
        'title' => '首頁顯示設定',
        'itemsPerPage' => 12,
        'columns' => [
            ['field' => 'published_at', 'label' => '日期', 'type' => 'date', 'width' => 142],
            ['field' => 'title', 'label' => '標題', 'type' => 'title', 'width' => 470],
            ['field' => 'home_sort', 'label' => '排序', 'type' => 'home_sort_dropdown', 'width' => 74],
            ['field' => 'is_in_home', 'label' => '首頁顯示', 'type' => 'home_display_toggle', 'width' => 100],
        ],
    ],
];
