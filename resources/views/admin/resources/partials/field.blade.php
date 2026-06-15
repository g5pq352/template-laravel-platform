@php
    $name = $field['name'];
    $type = $field['type'];
    $readonly = ($field['readonly'] ?? false) ? 'readonly' : '';
    $disabled = ($field['readonly'] ?? false) && in_array($type, ['select', 'taxonomy', 'linked_taxonomy'], true) ? 'disabled' : '';
    $required = ($field['required'] ?? false) ? 'required' : '';
    $fieldId = 'field_' . str_replace(['[', ']'], '_', $name);
    $mediaByRole = $mediaByRole ?? [];
    $fieldTaxonomyOptions = $taxonomyOptionsByField[$name] ?? $taxonomyOptions ?? [];
    $fieldTaxonomyTree = $taxonomyTreesByField[$name] ?? [];
    $fieldSelectedTermIds = old($name, $selectedTermIdsByField[$name] ?? ($name === 'term_ids' ? ($selectedTermIds ?? []) : []));
    $taxonomyInputName = $name . (!empty($field['multiple']) ? '[]' : '');
    $fieldLabel = $field['label'];
    $wideTypes = ['datetime', 'updatetime', 'image_upload', 'file_upload', 'dynamic_fields'];
    $colClass = $type === 'dynamic_fields'
        ? 'col-lg-7 col-xl-7'
        : (in_array($type, $wideTypes, true) ? 'col-lg-7' : 'col-lg-7 col-xl-7');
    $labelClass = in_array($type, $wideTypes, true)
        ? 'col-lg-2 control-label text-lg-end pt-2'
        : 'col-lg-5 col-xl-2 control-label text-lg-end ' . (in_array($type, ['textarea', 'editor'], true) ? 'pt-2 mt-1' : 'mb-0');
@endphp

@if(!($field['hide_on_create'] ?? false) || $item)
<div class="form-group row cms-form-row {{ in_array($type, ['textarea', 'editor', 'image_upload', 'file_upload', 'dynamic_fields'], true) ? '' : 'align-items-center' }}">
    <label class="{{ $labelClass }}">
        {{ $fieldLabel }}
        @if($required)<span class="required">*</span>@endif
    </label>
    <div class="{{ $colClass }}">
        @if($type === 'textarea' || $type === 'editor')
            @if(!empty($field['has_gallery']))
                <div class="gallery-opener btn btn-default mb-3" data-target="{{ $fieldId }}">打開圖片庫</div>
            @endif
            <textarea id="{{ $fieldId }}" class="form-control form-control-modern {{ $type === 'editor' ? 'tiny' : '' }}" name="{{ $name }}" rows="{{ $field['rows'] ?? 5 }}" cols="{{ $field['cols'] ?? 80 }}" {{ $readonly }} {{ $required }}>{{ $value }}</textarea>
        @elseif($type === 'select')
            <select class="form-control form-control-md" name="{{ $name }}" {{ $required }} {{ $disabled }}>
                @foreach($statusOptions as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            @if($disabled)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endif
        @elseif($type === 'taxonomy')
            <select id="{{ $fieldId }}" class="form-control" name="{{ $taxonomyInputName }}" data-plugin-selectTwo {{ !empty($field['multiple']) ? 'multiple' : '' }} {{ $disabled }}>
                @if(empty($field['multiple']))
                    <option value="">-- 請選擇 --</option>
                @endif
                @foreach($fieldTaxonomyOptions as $option)
                    <option value="{{ $option['id'] }}" @selected(in_array($option['id'], array_map('intval', (array) $fieldSelectedTermIds), true))>{{ $option['label'] }}</option>
                @endforeach
            </select>
        @elseif($type === 'linked_taxonomy')
            @php
                $selectedLinkedId = collect((array) $fieldSelectedTermIds)->filter()->last();
            @endphp
            <div
                class="linked-select-wrapper"
                data-field="{{ $name }}"
                data-category="{{ $field['category'] ?? '' }}"
                data-placeholder="-- 請選擇 --"
            >
                <input type="hidden" id="{{ $fieldId }}" name="{{ $taxonomyInputName }}" value="{{ $selectedLinkedId }}">
                <div class="linked-select-levels" data-input-id="{{ $fieldId }}"></div>
            </div>
            <script type="application/json" id="{{ $fieldId }}_tree">@json($fieldTaxonomyTree)</script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    window.initCmsLinkedTaxonomy?.(@json($fieldId), @json((int) $selectedLinkedId), {
                        select2: false
                    });
                });
            </script>
        @elseif($type === 'checkbox')
            <div class="checkbox-custom checkbox-default pt-2">
                <input id="{{ $name }}" type="checkbox" name="{{ $name }}" value="1" @checked((bool) $value)>
                <label for="{{ $name }}">{{ $field['label'] }}</label>
            </div>
        @elseif($type === 'datetime')
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input class="form-control" type="text" name="{{ $name }}" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s') }}" {{ $readonly }} {{ $required }}>
            </div>
        @elseif($type === 'updatetime')
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                <input class="form-control" type="text" name="{{ $name }}" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s') : '' }}" readonly>
            </div>
        @elseif($type === 'image_upload')
            @php
                $fileType = $field['file_type'] ?? $name;
                $maxSize = $field['maxSize'] ?? data_get($field, 'size.maxSize', 2);
                $firstSize = collect($field['size'] ?? [])->first(fn ($size) => is_array($size) && isset($size['w'], $size['h'])) ?? ['w' => 800, 'h' => 600];
                $width = (int) ($firstSize['w'] ?? 800);
                $height = (int) ($firstSize['h'] ?? 600);
                $existingImages = $mediaByRole[$fileType] ?? [];
                $hasImages = count($existingImages) > 0;
                $dimensionNote = $width > 0 && $height > 0 ? "* 建議尺寸：{$width}x{$height}px" : '';
                $finalNote = trim($dimensionNote . " (大小限制 {$maxSize}MB)" . (!empty($field['note']) ? ' <br>' . $field['note'] : ''));
            @endphp
            <script>
                window.GLOBAL_IMG_CONFIG = window.GLOBAL_IMG_CONFIG || {};
                window.GLOBAL_IMG_CONFIG[@json($fileType)] = {
                    IW: {{ $width }},
                    IH: {{ $height }},
                    OW: {{ $width }},
                    OH: {{ $height }}
                };
            </script>
            <div class="draggable_image" id="draggable_{{ $name }}" data-config="{{ $fileType }}" data-prefix="{{ $name }}" data-multiple="{{ !empty($field['multiple']) ? '1' : '0' }}" data-max-size="{{ $maxSize }}">
                @foreach($existingImages as $image)
                    <div class="image-manage-item" id="img_item_{{ $image['id'] }}" data-id="{{ $image['id'] }}">
                        <div style="display: flex; align-items: flex-start; margin-bottom: 10px; position:relative;">
                            @if(!empty($field['multiple']))
                                <div class="drag-handle" style="margin-right:10px; cursor:move; color:#ccc;" title="拖曳排序"><i class="fas fa-grip-vertical"></i></div>
                            @endif
                            <div style="width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;">
                                <a href="{{ $image['url'] }}" class="fancyboxImg" rel="group_{{ $name }}" title="{{ $image['title'] }}">
                                    <img src="{{ $image['url'] }}" id="croppedImagePreviewex_{{ $image['id'] }}" style="width:100%; height:100%; object-fit: cover; cursor: pointer;">
                                </a>
                            </div>
                            <div>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <input type="file" id="{{ $name }}_ex_{{ $image['id'] }}" name="{{ $name }}_update[{{ $image['id'] }}]" class="hidden-file-input" style="display:none;" accept="image/*">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <button type="button" class="trigger-crop-btn btn btn-default" data-target="{{ $name }}_ex_{{ $image['id'] }}">選擇檔案</button>
                                        @if(!empty($field['multiple']))
                                            <a href="javascript:void(0)" onclick="deleteImageItem({{ $image['id'] }})" style="color:#666; font-size:16px;" title="刪除圖片"><i class="fas fa-trash-alt"></i></a>
                                        @endif
                                    </div>
                                    <a href="javascript:void(0)" id="remove_btn_ex_{{ $image['id'] }}" style="color:red; text-decoration:none; font-size:14px; margin-top:5px; display:inline-block;"><i class="fas fa-times-circle"></i> 刪除</a>
                                </div>
                                <div style="margin-top: 5px;">
                                    <p id="fileNameDisplayex_{{ $image['id'] }}" class="file-name-display" style="display:none; font-size:0.9rem; color:#555;margin: 0;">未選擇任何檔案</p>
                                    <p id="uploadStatusex_{{ $image['id'] }}" class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                                    <input type="hidden" id="imageUrlex_{{ $image['id'] }}" class="url-input">
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:5px; display: flex; align-items: center;">
                            <span class="table_data" style="flex-shrink:0;">圖片說明：</span>
                            <input type="text" id="title_ex_{{ $image['id'] }}" name="update_file_title[{{ $image['id'] }}]" value="{{ $image['title'] }}" class="table_data" style="width: 300px; padding: 4px; border: 1px solid #ccc;">
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const cfg = window.GLOBAL_IMG_CONFIG[@json($fileType)] || { IW: 800, IH: 600 };
                            window.uploaders['ex_{{ $image['id'] }}'] = new LaravelImageUploader('ex_{{ $image['id'] }}', {
                                prefix: @json($name),
                                minWidth: cfg.IW,
                                minHeight: cfg.IH,
                                outputWidth: cfg.OW || cfg.IW,
                                outputHeight: cfg.OH || cfg.IH,
                                maxSize: {{ $maxSize }}
                            });
                        });
                    </script>
                @endforeach
            </div>
            @if(!empty($field['multiple']))
                <div style="margin-top:20px;">
                    <a href="javascript:void(0)" onclick="addDynamicField('draggable_{{ $name }}', '{{ $name }}', '{{ $fileType }}')" class="table_data" style="text-decoration:none;">
                        <img src="{{ asset('admin-assets/template-style/img/icons/add.png') }}" width="16" height="16" border="0" style="vertical-align:middle;" onerror="this.style.display='none';"> 新增圖片
                    </a>
                </div>
            @endif
            <label class="error mt-2">{!! $finalNote !!}</label>
            @if(!$hasImages)
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        if (typeof addDynamicField === 'function') {
                            addDynamicField('draggable_{{ $name }}', '{{ $name }}', '{{ $fileType }}', false);
                        }
                    });
                </script>
            @endif

            @once
                <div id="delete_file_container"></div>
                <template id="universalRowTemplate">
                    <div class="image-manage-item" data-id="">
                        <div style="display: flex; align-items: flex-start; margin-bottom: 10px; position:relative;">
                            <div class="drag-handle" style="margin-right:10px; cursor:move; color:#ccc; display:none;" title="拖曳排序"><i class="fas fa-grip-vertical"></i></div>
                            <div style="width:100px; height:100px; margin-right: 15px; border: 1px solid #ddd; overflow: hidden;">
                                <img class="preview-img cms-image-lightbox-trigger" src="{{ asset('admin-assets/cms-crop/demo.jpg') }}" data-cms-placeholder="1" title="預覽圖" style="width:100%; height:100%; object-fit: cover; cursor: pointer;">
                            </div>
                            <div>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <input type="file" class="hidden-file-input" accept="image/*" style="display:none;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <button type="button" class="trigger-crop-btn btn btn-default">選擇檔案</button>
                                        <a href="javascript:void(0)" class="trash-btn" style="color:#666; font-size:16px;" title="刪除整列"><i class="fas fa-trash-alt"></i></a>
                                    </div>
                                    <a href="javascript:void(0)" class="delete-row-btn" style="color:red; text-decoration:none; font-size:14px; margin-top:5px; display:none;"><i class="fas fa-times-circle"></i> 刪除</a>
                                </div>
                                <div style="margin-top: 5px;">
                                    <p class="file-name-display" style="display:none; font-size:0.9rem; color:#555;margin: 0;">未選擇任何檔案</p>
                                    <p class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; margin-top: 5px;">
                            <span class="table_data" style="flex-shrink:0;">圖片說明：</span>
                            <input type="text" class="title-input table_data" style="width: 300px; padding: 4px; border: 1px solid #ccc;">
                        </div>
                        <input type="hidden" class="url-input">
                    </div>
                </template>

                <div id="cropModal" class="modal-crop">
                    <div class="modal-content">
                        <h2>圖片裁切</h2>
                        <p>最低尺寸：<span id="minDimensions"></span> | 目前尺寸：<span id="currentDimensions" style="font-weight:bold;">0 x 0</span></p>
                        <div class="img-container"><img id="imageToCrop" src="" alt=""></div>
                        <div id="cropProgressMsg" style="display:none; text-align:center; color:#007bff; font-weight:bold; margin: 10px 0;">圖片裁切中，請稍候...</div>
                        <div style="text-align: center; margin-top: 15px;">
                            <button id="confirmCropBtn" type="button" class="crop-action-btn btn-green">確認</button>
                            <button id="forceCropBtn" type="button" class="crop-action-btn btn-red">強制</button>
                            <button id="cancelCropBtn" type="button" class="crop-action-btn btn-gray">取消</button>
                        </div>
                    </div>
                </div>
            @endonce
        @elseif($type === 'dynamic_fields')
            @include('admin.resources.partials.dynamic-fields', [
                'field' => $field,
                'value' => $value,
                'name' => $name,
            ])
        @elseif($type === 'file_upload')
            <input class="form-control" type="file" name="{{ $name }}{{ !empty($field['multiple']) ? '[]' : '' }}" {{ !empty($field['multiple']) ? 'multiple' : '' }}>
        @else
            <input class="form-control form-control-modern" type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $name }}" id="{{ $fieldId }}" value="{{ $value }}" step="{{ $field['step'] ?? '1' }}" {{ $readonly }} {{ $required }} @if(!empty($field['check_duplicate'])) data-check-duplicate="1" @endif>
        @endif

        @if(!empty($field['note']))
            <label class="error mt-2">{!! $field['note'] !!}</label>
        @endif
    </div>
</div>
@endif

