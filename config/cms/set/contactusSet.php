<?php

use App\Models\ContactMessage;

return [
    'adminKey' => 'contact',
    'module' => 'contactus',
    'moduleName' => '聯絡我們管理',
    'model' => ContactMessage::class,
    'strategy' => 'contact',
    'show_add_button' => false,
    'readonly' => true,

    'listPage' => [
        'columns' => [
            ['field' => 'm_date', 'label' => '日期', 'type' => 'date', 'width' => 120],
            ['field' => 'm_title', 'label' => '主旨', 'type' => 'title', 'width' => 200],
            ['field' => 'read', 'label' => '已讀', 'type' => 'read', 'width' => 60],
            ['field' => 'view', 'label' => '查看', 'type' => 'button', 'width' => 30],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => 30],
        ],
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'readonly' => true,
            'items' => [
                ['type' => 'text', 'field' => 'm_inquiry', 'label' => '詢問項目', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_title', 'label' => '主旨', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_name', 'label' => '姓名', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_email', 'label' => '電子信箱', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_phone', 'label' => '電話', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_address', 'label' => '聯絡地址', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_data1', 'label' => '方便聯絡時間', 'readonly' => true],
                ['type' => 'text', 'field' => 'm_data2', 'label' => '聯絡日期', 'readonly' => true],
                ['type' => 'textarea', 'field' => 'm_content', 'label' => '內容', 'rows' => 6, 'readonly' => true],
                ['type' => 'datetime', 'field' => 'm_date', 'label' => '日期', 'readonly' => true],
            ],
        ],
    ],
];
