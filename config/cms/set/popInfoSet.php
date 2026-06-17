<?php

return [
    'moduleName' => '燈箱設定',
    'slug' => 'info-pop',
    'hasLanguage' => true,

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                [
                    'type' => 'image_upload',
                    'field' => 'imageCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'popInfoCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [['w' => 1920, 'h' => 1080]],
                    'maxSize' => 5,
                ],
                ['type' => 'datetime', 'field' => 'd_date', 'label' => '日期', 'default' => 'now'],
                ['type' => 'select', 'field' => 'd_active', 'label' => '網頁顯示狀態', 'default' => 'published'],
            ],
        ],
    ],
];
