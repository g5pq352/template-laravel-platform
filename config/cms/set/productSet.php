<?php

use App\Models\Product;

$module = 'product';
$category = 'productCate';
$hasHierarchy = true;

return [
    'adminKey' => 'products',
    'module' => $module,
    'moduleName' => '產品管理',
    'model' => Product::class,
    'strategy' => 'product',
    'hasLanguage' => true,

    'listPage' => [
        'imageFileType' => 'productCover',
        'categoryName' => $category,
        'categoryField' => ['d_class2', 'd_class3', 'd_class4', 'd_class5'],
        'columns' => [
            ['field' => 'd_sort', 'label' => '排序', 'type' => 'sort', 'width' => 74],
            ['field' => 'pin', 'label' => '置頂', 'type' => 'button', 'width' => 50],
            ['field' => 'd_date', 'label' => '日期', 'type' => 'date', 'width' => 142],
            ['field' => 'd_title', 'label' => '標題', 'type' => 'title', 'width' => 470],
            ['field' => 'd_view', 'label' => '瀏覽次數', 'type' => 'view_count', 'width' => 60],
            ['field' => 'image', 'label' => '圖片', 'type' => 'image', 'width' => 140],
            ['field' => 'd_active', 'label' => '狀態', 'type' => 'active', 'width' => 60],
            ['field' => 'd_class2', 'label' => '分類屬性', 'type' => 'category_path', 'width' => 134],
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
                    'field' => ['d_class2', 'd_class3', 'd_class4', 'd_class5'],
                    'label' => '上層分類',
                    'category' => $category,
                    'linked' => $hasHierarchy,
                ],
                [
                    'type' => 'select',
                    'field' => 'd_tag',
                    'label' => '標籤',
                    'category' => 'productTag',
                    'multiple' => true,
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
                ['type' => 'datetime', 'field' => 'd_date', 'label' => '日期'],
                ['type' => 'updatetime', 'field' => 'd_update_time', 'label' => '最後更新時間', 'readonly' => true, 'hide_on_create' => true],
                ['type' => 'select', 'field' => 'd_active', 'label' => '在網頁顯示'],
                [
                    'type' => 'image_upload',
                    'field' => 'productCover',
                    'label' => '上傳封面圖片',
                    'fileType' => 'productCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [['w' => 1030, 'h' => 570]],
                ],
                [
                    'type' => 'image_upload',
                    'field' => 'productMCover',
                    'label' => '上傳封面手機圖片',
                    'fileType' => 'productMCover',
                    'multiple' => false,
                    'dropzone' => false,
                    'size' => [['w' => 1030, 'h' => 570]],
                ],
            ],
        ],
        [
            'sheetTitle' => 'SEO設定',
            'items' => [
                ['type' => 'text', 'field' => 'd_slug', 'label' => '網址別名 (slug)', 'note' => '留空則自動從標題產生'],
                ['type' => 'text', 'field' => 'd_seo_title', 'label' => 'SEO 標題', 'note' => '建議長度：50-60 字元'],
                ['type' => 'textarea', 'field' => 'd_description', 'label' => 'SEO 描述 (meta description)', 'rows' => 4, 'cols' => 80, 'note' => '建議長度：150-160 字元'],
            ],
        ],
    ],

    'hiddenFields' => [
        'd_class1' => $module,
    ],
];
