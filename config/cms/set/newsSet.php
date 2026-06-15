<?php

use App\Models\Content;

$module = 'news';
$category = 'newsCate';
$hasHierarchy = false;

return [
    'module' => $module,
    'moduleName' => '最新消息管理',
    'model' => Content::class,
    'strategy' => 'content',
    'content_type' => 'news',

    'listPage' => [
        'imageFileType' => 'newsCover',
        'categoryName' => $category,
        'categoryField' => $hasHierarchy ? ['d_class2', 'd_class3'] : 'd_class2',
        'hasCategory' => true,
        'useTaxonomyMapSort' => true,
        'globalSort' => true,
        'columns' => [
            ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => 74],
            ['field' => 'pin', 'label' => '置頂', 'type' => 'button', 'width' => 50],
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => 142],
            ['field' => 'd_title', 'label' => '標題', 'type' => 'title', 'width' => 470],
            ['field' => 'd_view', 'label' => '瀏覽次數', 'type' => 'view_count', 'width' => 60],
            ['field' => 'image', 'label' => '圖片', 'type' => 'image', 'width' => 140],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => 60],
            ['field' => 'edit', 'label' => '編輯', 'type' => 'button', 'width' => 30],
            ['field' => 'delete', 'label' => '刪除', 'type' => 'button', 'width' => 30],
        ],
    ],

    'detailPage' => [
        [
            'sheetTitle' => '資料設定',
            'items' => [
                [
                    'type' => 'select',
                    'field' => $hasHierarchy ? ['d_class2', 'd_class3'] : 'd_class2',
                    'label' => $hasHierarchy ? '層級分類' : '分類',
                    'category' => $category,
                    'linked' => $hasHierarchy,
                ],
                [
                    'type' => 'text',
                    'field' => 'd_title',
                    'label' => '標題',
                    'required' => true,
                    'checkDuplicate' => true,
                ],
                [
                    'type' => 'editor',
                    'field' => 'd_content',
                    'label' => '內容',
                    'rows' => 6,
                    'cols' => 80,
                    'useTiny' => true,
                    'hasGallery' => true,
                    'note' => '*小斷行請按Shift+Enter。<br>輸入區域的右下角可以調整輸入空間的大小。',
                ],
                [
                    'type' => 'datetime',
                    'field' => 'd_date',
                    'label' => '日期',
                ],
                [
                    'type' => 'select',
                    'field' => 'd_active',
                    'label' => '在網頁顯示',
                ],
                [
                    'type' => 'image_upload',
                    'field' => 'newsCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'newsCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1030, 'h' => 570],
                    ],
                ],
                [
                    'type' => 'image_upload',
                    'field' => 'newsMCover',
                    'label' => '上傳封面手機圖片',
                    'fileType' => 'newsMCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [
                        ['w' => 1030, 'h' => 570],
                    ],
                ],
            ],
        ],
        [
            'sheetTitle' => '房型設定',
            'items' => [
                [
                    'type' => 'dynamic_fields',
                    'field' => 'dynamic_rooms',
                    'label' => '房型資訊',
                    'fieldGroup' => 'rooms',
                    'fields' => [
                        ['name' => 'room_ch', 'label' => '中文名稱', 'type' => 'text', 'required' => true],
                        ['name' => 'room_en', 'label' => '英文名稱', 'type' => 'text'],
                        [
                            'name' => 'room_type',
                            'label' => '房型類別',
                            'type' => 'select',
                            'options' => [
                                ['value' => 'single', 'label' => '單人房'],
                                ['value' => 'double', 'label' => '雙人房'],
                                ['value' => 'triple', 'label' => '三人房'],
                                ['value' => 'quad', 'label' => '四人房'],
                            ],
                        ],
                        ['name' => 'room_content', 'label' => '房型說明', 'type' => 'textarea'],
                        [
                            'name' => 'room_image',
                            'label' => '房型圖片',
                            'type' => 'image',
                            'fileType' => 'roomImage',
                            'size' => [
                                ['w' => 800, 'h' => 600],
                                'maxSize' => 3,
                            ],
                        ],
                        [
                            'name' => 'room_file',
                            'label' => '附件下載',
                            'type' => 'file',
                            'format' => '.pdf,.jpg,.png',
                            'size' => [
                                'maxSize' => 8,
                            ],
                            'fileType' => 'roomfile',
                        ],
                    ],
                    'note' => '點擊「+」可以新增更多項目',
                ],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                [
                    'type' => 'text',
                    'field' => 'd_slug',
                    'label' => '網址別名 (slug)',
                    'note' => '留空則自動從標題產生',
                ],
                [
                    'type' => 'text',
                    'field' => 'd_seo_title',
                    'label' => 'SEO 標題',
                    'note' => '建議長度：50-60 字元',
                ],
                [
                    'type' => 'textarea',
                    'field' => 'd_description',
                    'label' => 'SEO 描述 (meta description)',
                    'rows' => 4,
                    'cols' => 80,
                    'note' => '建議長度：150-160 字元',
                ],
            ],
        ],
    ],

    'hiddenFields' => [
        'd_class1' => $module,
    ],
];
