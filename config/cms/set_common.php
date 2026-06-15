<?php

return [
    'tables' => [
        'default' => [
            'tableName' => 'data_set',
            'primaryKey' => 'd_id',
            'menuKey' => 'd_class1',
        ],
        'contact' => [
            'tableName' => 'message_set',
            'primaryKey' => 'm_id',
            'menuKey' => 'm_type',
        ],
    ],

    'status_options' => [
        'content' => [
            'draft' => '草稿',
            'published' => '顯示',
            'hidden' => '不顯示',
        ],
        'product' => [
            'draft' => '草稿',
            'active' => '顯示',
            'hidden' => '不顯示',
        ],
        'contact' => [
            'pending' => '未處理',
            'processing' => '處理中',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ],
        'info' => [
            'published' => '顯示',
            'hidden' => '不顯示',
        ],
    ],

    'list' => [
        'show_add_button' => true,
        'slug' => true,
        'sort_column' => 'sort_order',
    ],

    'info' => [
        'strategy' => 'content',
        'status' => false,
    ],

    'strategies' => [
        'content' => [
            'category_relation' => 'terms',
        ],
        'product' => [
            'category_relation' => 'categories',
        ],
        'contact' => [
            'show_add_button' => false,
            'slug' => false,
            'sort_column' => 'created_at',
        ],
    ],
];
