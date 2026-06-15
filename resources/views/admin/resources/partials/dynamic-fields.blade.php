@php
    $dynamicRows = collect(is_array($value) ? $value : [])->values();
    $dynamicFields = $field['fields'] ?? [];
@endphp

<div class="cms-dynamic-fields" data-dynamic-wrapper data-field-name="{{ $name }}">
    <div class="dynamic-fields-container" data-dynamic-container>
        @foreach($dynamicRows as $rowIndex => $row)
            <div class="dynamic-field-group" data-dynamic-row draggable="true">
                <input type="hidden" name="{{ $name }}[{{ $rowIndex }}][_uid]" value="{{ $row['_uid'] ?? (string) \Illuminate\Support\Str::uuid() }}">
                <div class="dynamic-field-shell">
                    <div class="dynamic-field-drag" title="拖曳排序">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="dynamic-field-panel">
                        <div class="dynamic-field-group-header">
                            <div class="dynamic-header-left">
                                <span class="dynamic-row-title">項目 <span data-row-number>{{ $loop->iteration }}</span></span>
                                <button type="button" class="dynamic-toggle-group" data-dynamic-toggle title="展開/收合">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                            <button type="button" class="dynamic-remove-group" data-dynamic-remove>
                                <i class="fas fa-trash"></i> 刪除
                            </button>
                        </div>

                        <div class="dynamic-field-group-body">
                            @foreach($dynamicFields as $subField)
                                @php
                                    $subName = $subField['name'];
                                    $subType = $subField['type'] ?? 'text';
                                    $subValue = $row[$subName] ?? null;
                                    $inputName = "{$name}[{$rowIndex}][{$subName}]";
                                    $required = !empty($subField['required']);
                                    $label = $subField['label'] ?? $subName;
                                    $sizeConfig = $subField['size'] ?? [];
                                    $maxSize = $subField['maxSize'] ?? data_get($sizeConfig, 'maxSize');
                                    $firstSize = collect($sizeConfig)->first(fn ($size) => is_array($size) && isset($size['w'], $size['h']));
                                    $dimensionText = $firstSize ? "{$firstSize['w']}x{$firstSize['h']}px" : null;
                                    $fileInfo = is_array($subValue) ? $subValue : null;
                                    $inputId = 'df_' . md5($inputName . $subName);
                                    $format = $subField['format'] ?? '';
                                @endphp

                                <div class="dynamic-subfield {{ in_array($subType, ['image', 'file'], true) ? 'dynamic-subfield-media' : '' }}">
                                    <label class="dynamic-subfield-label">
                                        {{ $label }}
                                        @if($required)<span class="required">*</span>@endif
                                    </label>

                                    @if($subType === 'textarea')
                                        <textarea class="form-control" name="{{ $inputName }}" rows="{{ $subField['rows'] ?? 4 }}" @if($required) required @endif>{{ is_array($subValue) ? '' : $subValue }}</textarea>
                                    @elseif($subType === 'select')
                                        <select class="form-control" name="{{ $inputName }}" @if($required) required @endif>
                                            <option value="">請選擇</option>
                                            @foreach($subField['options'] ?? [] as $option)
                                                @php
                                                    $optionValue = is_array($option) ? ($option['value'] ?? '') : $option;
                                                    $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                                @endphp
                                                <option value="{{ $optionValue }}" @selected((string) $subValue === (string) $optionValue)>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($subType === 'image')
                                        <div class="dynamic-image-line">
                                            <div class="dynamic-image-preview">
                                                @if($fileInfo && !empty($fileInfo['url']))
                                                    <a href="{{ $fileInfo['url'] }}" class="fancyboxImg" target="_blank">
                                                        <img src="{{ $fileInfo['url'] }}" alt="">
                                                    </a>
                                                    <input type="hidden" name="{{ $inputName }}[_existing]" value="{{ base64_encode(json_encode($fileInfo, JSON_UNESCAPED_UNICODE)) }}">
                                                @else
                                                    <img src="{{ asset('admin-assets/cms-crop/demo.jpg') }}" alt="">
                                                @endif
                                            </div>
                                            <div class="dynamic-file-controls">
                                                <input id="{{ $inputId }}" class="dynamic-hidden-file" type="file" name="{{ $inputName }}" accept="image/*" @if($required && !$fileInfo) required @endif>
                                                <button type="button" class="btn btn-default dynamic-trigger-file">選擇檔案</button>
                                                <span class="dynamic-file-name">{{ $fileInfo['original_name'] ?? '未選擇任何檔案' }}</span>
                                            </div>
                                        </div>
                                        <div class="dynamic-media-title">
                                            <span>圖片說明：</span>
                                            <input type="text" name="{{ $inputName }}[_title]" value="{{ $fileInfo['title'] ?? $fileInfo['alt_text'] ?? '' }}">
                                        </div>
                                        @if($dimensionText || $maxSize || !empty($subField['note']))
                                            <label class="error dynamic-note">
                                                * @if($dimensionText)建議尺寸：{{ $dimensionText }} @endif
                                                @if($maxSize)(大小限制 {{ $maxSize }}MB)@endif
                                                {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
                                            </label>
                                        @endif
                                    @elseif($subType === 'file')
                                        <div class="dynamic-file-row">
                                            @if($fileInfo && !empty($fileInfo['url']))
                                                <a href="{{ $fileInfo['url'] }}" target="_blank" class="dynamic-existing-file">{{ $fileInfo['original_name'] ?? '已上傳檔案' }}</a>
                                                <input type="hidden" name="{{ $inputName }}[_existing]" value="{{ base64_encode(json_encode($fileInfo, JSON_UNESCAPED_UNICODE)) }}">
                                            @endif
                                            <input id="{{ $inputId }}" class="dynamic-hidden-file" type="file" name="{{ $inputName }}" accept="{{ $format }}" @if($required && !$fileInfo) required @endif>
                                            <button type="button" class="btn btn-default dynamic-trigger-file">選擇檔案</button>
                                            <span class="dynamic-file-name">{{ $fileInfo['original_name'] ?? '未選擇任何檔案' }}</span>
                                            <input class="dynamic-file-title" type="text" name="{{ $inputName }}[_title]" value="{{ $fileInfo['title'] ?? '' }}" placeholder="檔案說明">
                                        </div>
                                        @if($format || $maxSize || !empty($subField['note']))
                                            <label class="error dynamic-note">
                                                * @if($format)支援格式：{{ str_replace('.', '', $format) }} @endif
                                                @if($maxSize)(大小限制 {{ $maxSize }}MB)@endif
                                                {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
                                            </label>
                                        @endif
                                    @else
                                        <input class="form-control" type="{{ $subType === 'number' ? 'number' : 'text' }}" name="{{ $inputName }}" value="{{ is_array($subValue) ? '' : $subValue }}" @if($required) required @endif>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dynamic-fields-footer">
        <a href="javascript:void(0)" class="dynamic-add-link" data-dynamic-add>
            <i class="fas fa-plus-circle"></i> 新增項目
        </a>
    </div>

    <div class="dynamic-fields-note">
        <i class="fas fa-info-circle"></i> 點擊「+」可以新增更多項目
    </div>

    <template data-dynamic-template>
        <div class="dynamic-field-group" data-dynamic-row draggable="true">
            <input type="hidden" name="{{ $name }}[__INDEX__][_uid]" value="">
            <div class="dynamic-field-shell">
                <div class="dynamic-field-drag" title="拖曳排序">
                    <i class="fas fa-grip-vertical"></i>
                </div>
                <div class="dynamic-field-panel">
                    <div class="dynamic-field-group-header">
                        <div class="dynamic-header-left">
                            <span class="dynamic-row-title">項目 <span data-row-number></span></span>
                            <button type="button" class="dynamic-toggle-group" data-dynamic-toggle title="展開/收合">
                                <i class="fas fa-chevron-up"></i>
                            </button>
                        </div>
                        <button type="button" class="dynamic-remove-group" data-dynamic-remove>
                            <i class="fas fa-trash"></i> 刪除
                        </button>
                    </div>
                    <div class="dynamic-field-group-body">
                        @foreach($dynamicFields as $subField)
                            @php
                                $subName = $subField['name'];
                                $subType = $subField['type'] ?? 'text';
                                $inputName = "{$name}[__INDEX__][{$subName}]";
                                $required = !empty($subField['required']);
                                $label = $subField['label'] ?? $subName;
                                $sizeConfig = $subField['size'] ?? [];
                                $maxSize = $subField['maxSize'] ?? data_get($sizeConfig, 'maxSize');
                                $firstSize = collect($sizeConfig)->first(fn ($size) => is_array($size) && isset($size['w'], $size['h']));
                                $dimensionText = $firstSize ? "{$firstSize['w']}x{$firstSize['h']}px" : null;
                                $format = $subField['format'] ?? '';
                            @endphp

                            <div class="dynamic-subfield {{ in_array($subType, ['image', 'file'], true) ? 'dynamic-subfield-media' : '' }}">
                                <label class="dynamic-subfield-label">
                                    {{ $label }}
                                    @if($required)<span class="required">*</span>@endif
                                </label>

                                @if($subType === 'textarea')
                                    <textarea class="form-control" name="{{ $inputName }}" rows="{{ $subField['rows'] ?? 4 }}" @if($required) required @endif></textarea>
                                @elseif($subType === 'select')
                                    <select class="form-control" name="{{ $inputName }}" @if($required) required @endif>
                                        <option value="">請選擇</option>
                                        @foreach($subField['options'] ?? [] as $option)
                                            @php
                                                $optionValue = is_array($option) ? ($option['value'] ?? '') : $option;
                                                $optionLabel = is_array($option) ? ($option['label'] ?? $optionValue) : $option;
                                            @endphp
                                            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif($subType === 'image')
                                    <div class="dynamic-image-line">
                                        <div class="dynamic-image-preview">
                                            <img src="{{ asset('admin-assets/cms-crop/demo.jpg') }}" alt="">
                                        </div>
                                        <div class="dynamic-file-controls">
                                            <input class="dynamic-hidden-file" type="file" name="{{ $inputName }}" accept="image/*" @if($required) required @endif>
                                            <button type="button" class="btn btn-default dynamic-trigger-file">選擇檔案</button>
                                            <span class="dynamic-file-name">未選擇任何檔案</span>
                                        </div>
                                    </div>
                                    <div class="dynamic-media-title">
                                        <span>圖片說明：</span>
                                        <input type="text" name="{{ $inputName }}[_title]">
                                    </div>
                                    @if($dimensionText || $maxSize || !empty($subField['note']))
                                        <label class="error dynamic-note">
                                            * @if($dimensionText)建議尺寸：{{ $dimensionText }} @endif
                                            @if($maxSize)(大小限制 {{ $maxSize }}MB)@endif
                                            {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
                                        </label>
                                    @endif
                                @elseif($subType === 'file')
                                    <div class="dynamic-file-row">
                                        <input class="dynamic-hidden-file" type="file" name="{{ $inputName }}" accept="{{ $format }}" @if($required) required @endif>
                                        <button type="button" class="btn btn-default dynamic-trigger-file">選擇檔案</button>
                                        <span class="dynamic-file-name">未選擇任何檔案</span>
                                        <input class="dynamic-file-title" type="text" name="{{ $inputName }}[_title]" placeholder="檔案說明">
                                    </div>
                                    @if($format || $maxSize || !empty($subField['note']))
                                        <label class="error dynamic-note">
                                            * @if($format)支援格式：{{ str_replace('.', '', $format) }} @endif
                                            @if($maxSize)(大小限制 {{ $maxSize }}MB)@endif
                                            {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
                                        </label>
                                    @endif
                                @else
                                    <input class="form-control" type="{{ $subType === 'number' ? 'number' : 'text' }}" name="{{ $inputName }}" @if($required) required @endif>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    <style>
        .cms-dynamic-fields{margin:0;padding:0;background:transparent;border:0}
        .cms-dynamic-fields .dynamic-fields-container{min-height:0;margin-bottom:25px}
        .cms-dynamic-fields .dynamic-field-group{margin-bottom:25px}
        .cms-dynamic-fields .dynamic-field-shell{display:flex;align-items:flex-start}
        .cms-dynamic-fields .dynamic-field-drag{width:26px;padding-top:28px;color:#c7c7c7;font-size:18px;cursor:move;text-align:center;user-select:none;flex-shrink:0}
        .cms-dynamic-fields .dynamic-field-drag:hover{color:#888}
        .cms-dynamic-fields .dynamic-field-panel{flex:1;background:#fff;border:1px solid #d8d8d8;border-radius:4px;box-shadow:0 1px 3px rgba(0,0,0,.05);padding:20px 20px 0}
        .cms-dynamic-fields .dynamic-field-group-header{display:flex;align-items:center;justify-content:space-between;padding-bottom:12px;margin-bottom:0;border-bottom:1px solid #eee}
        .cms-dynamic-fields .dynamic-header-left{display:flex;align-items:center;gap:8px}
        .cms-dynamic-fields .dynamic-row-title{display:inline-flex;align-items:center;min-height:31px;padding:0 13px;background:#f5f5f5;border-radius:3px;color:#555;font-size:14px;font-weight:600}
        .cms-dynamic-fields .dynamic-toggle-group{width:32px;height:30px;border:0;border-radius:3px;background:#6c757d;color:#fff;font-size:13px;line-height:1;cursor:pointer}
        .cms-dynamic-fields .dynamic-remove-group{height:34px;padding:0 18px;border:0;border-radius:3px;background:#dc3545;color:#fff;font-size:13px;font-weight:600;cursor:pointer}
        .cms-dynamic-fields .dynamic-field-group.collapsed .dynamic-field-panel{padding-bottom:20px}
        .cms-dynamic-fields .dynamic-field-group.collapsed .dynamic-field-group-header{border-bottom:0;padding-bottom:0}
        .cms-dynamic-fields .dynamic-field-group.collapsed .dynamic-field-group-body{display:none}
        .cms-dynamic-fields .dynamic-subfield{padding:18px 0;border-bottom:1px solid #eee}
        .cms-dynamic-fields .dynamic-subfield:last-child{border-bottom:0}
        .cms-dynamic-fields .dynamic-subfield-label{display:block;margin-bottom:10px;color:#555;font-size:13px;font-weight:600}
        .cms-dynamic-fields .form-control{width:100%;height:46px;padding:10px 12px;border:1px solid #d6d6d6;border-radius:3px;background:#fff;color:#333;font-size:13px;box-shadow:none}
        .cms-dynamic-fields select.form-control{padding-right:34px;appearance:auto;-webkit-appearance:menulist;-moz-appearance:auto;background-color:#fff}
        .cms-dynamic-fields textarea.form-control{height:auto;min-height:98px;resize:vertical}
        .cms-dynamic-fields .dynamic-image-line{display:flex;align-items:flex-start;gap:15px}
        .cms-dynamic-fields .dynamic-image-preview{width:100px;height:100px;border:1px solid #ddd;background:#000;overflow:hidden;flex-shrink:0}
        .cms-dynamic-fields .dynamic-image-preview img{width:100%;height:100%;object-fit:cover;display:block}
        .cms-dynamic-fields .dynamic-hidden-file{display:none}
        .cms-dynamic-fields .dynamic-file-controls{display:flex;align-items:center;gap:12px;min-height:40px;flex-wrap:wrap}
        .cms-dynamic-fields .dynamic-file-name{color:#333;font-size:13px}
        .cms-dynamic-fields .dynamic-media-title{display:flex;align-items:center;gap:5px;margin-top:10px;color:#555;font-size:13px}
        .cms-dynamic-fields .dynamic-media-title input{width:300px;height:32px;border:1px solid #ccc;padding:4px 8px}
        .cms-dynamic-fields .dynamic-file-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .cms-dynamic-fields .dynamic-file-title{width:200px;height:46px;border:1px solid #d6d6d6;border-radius:3px;padding:10px 12px}
        .cms-dynamic-fields .dynamic-existing-file{color:#0088cc;font-size:13px}
        .cms-dynamic-fields .dynamic-note{display:block;margin-top:10px;color:#c10000;font-size:13px;font-weight:400}
        .cms-dynamic-fields .dynamic-fields-footer{margin-top:5px;padding-top:16px;border-top:1px solid #e8e8e8}
        .cms-dynamic-fields .dynamic-add-link{display:inline-flex;align-items:center;gap:7px;color:#333;font-size:13px;text-decoration:none}
        .cms-dynamic-fields .dynamic-add-link i{color:#5cb85c}
        .cms-dynamic-fields .dynamic-add-link:hover{color:#0088cc;text-decoration:none}
        .cms-dynamic-fields .dynamic-fields-note{margin-top:18px;padding:11px 16px;border:1px solid #ffc107;border-left:4px solid #ffc107;background:#fff3cd;color:#856404;border-radius:3px;font-size:12px}
        .cms-dynamic-fields .sortable-drag{opacity:.6}
        .cms-dynamic-fields .sortable-placeholder .dynamic-field-panel{border-style:dashed;background:#f7f7f7}
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function createUid() {
                return (window.crypto && window.crypto.randomUUID) ? window.crypto.randomUUID() : 'uid_' + Date.now() + '_' + Math.random().toString(36).slice(2);
            }

            document.querySelectorAll('[data-dynamic-wrapper]').forEach(function (wrapper) {
                const fieldName = wrapper.dataset.fieldName;
                const container = wrapper.querySelector('[data-dynamic-container]');
                const template = wrapper.querySelector('[data-dynamic-template]');
                const addButton = wrapper.querySelector('[data-dynamic-add]');

                function renumber() {
                    container.querySelectorAll('[data-dynamic-row]').forEach(function (row, index) {
                        row.querySelectorAll('[data-row-number]').forEach(function (number) {
                            number.textContent = index + 1;
                        });
                        row.querySelectorAll('[name]').forEach(function (input) {
                            input.name = input.name.replace(new RegExp(fieldName + '\\[[^\\]]+\\]'), fieldName + '[' + index + ']');
                        });
                    });
                }

                function bindFileControls(row) {
                    row.querySelectorAll('.dynamic-trigger-file').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const input = button.closest('.dynamic-file-controls, .dynamic-file-row')?.querySelector('input[type=file]');
                            input?.click();
                        });
                    });

                    row.querySelectorAll('input[type=file]').forEach(function (input) {
                        input.addEventListener('change', function () {
                            const file = input.files && input.files[0];
                            const wrap = input.closest('.dynamic-file-controls, .dynamic-file-row');
                            const name = wrap?.querySelector('.dynamic-file-name');
                            if (name) {
                                name.textContent = file ? file.name : '未選擇任何檔案';
                            }

                            if (file && file.type.startsWith('image/')) {
                                const preview = input.closest('.dynamic-subfield')?.querySelector('.dynamic-image-preview img');
                                if (preview) {
                                    preview.src = URL.createObjectURL(file);
                                }
                            }
                        });
                    });
                }

                function bindRow(row) {
                    const uidInput = row.querySelector('input[name$="[_uid]"]');
                    if (uidInput && !uidInput.value) {
                        uidInput.value = createUid();
                    }

                    row.querySelector('[data-dynamic-remove]')?.addEventListener('click', function () {
                        row.remove();
                        renumber();
                    });

                    row.querySelector('[data-dynamic-toggle]')?.addEventListener('click', function () {
                        row.classList.toggle('collapsed');
                        const icon = row.querySelector('[data-dynamic-toggle] i');
                        if (icon) {
                            icon.className = row.classList.contains('collapsed') ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
                        }
                    });

                    row.addEventListener('dragstart', function (event) {
                        row.classList.add('sortable-drag');
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', '');
                    });

                    row.addEventListener('dragend', function () {
                        row.classList.remove('sortable-drag');
                        container.querySelectorAll('.sortable-placeholder').forEach(function (item) {
                            item.classList.remove('sortable-placeholder');
                        });
                        renumber();
                    });

                    row.addEventListener('dragover', function (event) {
                        event.preventDefault();
                        const dragging = container.querySelector('.sortable-drag');
                        if (!dragging || dragging === row) {
                            return;
                        }

                        const rect = row.getBoundingClientRect();
                        if (event.clientY > rect.top + rect.height / 2) {
                            row.after(dragging);
                        } else {
                            row.before(dragging);
                        }
                        row.classList.add('sortable-placeholder');
                    });

                    row.addEventListener('dragleave', function () {
                        row.classList.remove('sortable-placeholder');
                    });

                    bindFileControls(row);
                }

                container.querySelectorAll('[data-dynamic-row]').forEach(bindRow);

                addButton?.addEventListener('click', function () {
                    const index = container.querySelectorAll('[data-dynamic-row]').length;
                    const fragment = document.createElement('div');
                    fragment.innerHTML = template.innerHTML.replaceAll('__INDEX__', index).trim();
                    const row = fragment.firstElementChild;
                    const uidInput = row.querySelector('input[name$="[_uid]"]');
                    if (uidInput) {
                        uidInput.value = createUid();
                    }
                    container.appendChild(row);
                    bindRow(row);
                    renumber();
                });
            });
        });
    </script>
@endonce
