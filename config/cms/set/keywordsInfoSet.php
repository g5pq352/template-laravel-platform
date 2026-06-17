<?php

return [
    'moduleName' => '全站設定',
    'slug' => 'info-keywords',
    'hasLanguage' => true,

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_title', 'label' => '網站名稱', 'required' => true],
                ['type' => 'text', 'field' => 'd_data1', 'label' => '電話'],
                ['type' => 'text', 'field' => 'd_data2', 'label' => '傳真'],
                ['type' => 'text', 'field' => 'd_data3', 'label' => '信箱'],
                ['type' => 'text', 'field' => 'd_data4', 'label' => '地址'],
                ['type' => 'text', 'field' => 'd_data5', 'label' => 'LINE ID'],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_seo_title', 'label' => 'SEO 標題', 'note' => '建議長度：50-60 字元'],
                ['type' => 'textarea', 'field' => 'd_description', 'label' => 'SEO 描述', 'rows' => 5, 'note' => '建議長度：150-160 字元'],
                ['type' => 'textarea', 'field' => 'd_head', 'label' => 'Head', 'rows' => 5],
                ['type' => 'textarea', 'field' => 'd_body', 'label' => 'Body', 'rows' => 5],
                ['type' => 'textarea', 'field' => 'd_schema', 'label' => 'Schema', 'rows' => 8],
            ],
        ],
    ],
];
