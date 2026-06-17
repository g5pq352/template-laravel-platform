<div class="dynamic-subfield {{ in_array($subType, ['image', 'image_upload', 'file', 'file_upload'], true) ? 'dynamic-subfield-media' : '' }}">
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
    @elseif(in_array($subType, ['image', 'image_upload'], true))
        <div class="image-manage-item dynamic-image-upload-item">
            <div style="display:flex; align-items:flex-start; margin-bottom:10px; position:relative;">
                <div class="dynamic-image-preview" style="width:100px; height:100px; margin-right:15px; border:1px solid #ddd; overflow:hidden; flex-shrink:0;">
                    @if($fileInfo && !empty($fileInfo['url']))
                        <img src="{{ $fileInfo['url'] }}" alt="" class="preview-img cms-image-lightbox-trigger" data-cms-placeholder="0" style="width:100%; height:100%; object-fit:cover; cursor:pointer;">
                        <input type="hidden" name="{{ $inputName }}[_existing]" value="{{ base64_encode(json_encode($fileInfo, JSON_UNESCAPED_UNICODE)) }}">
                    @else
                        <img src="{{ asset('admin-assets/cms-crop/demo.jpg') }}" alt="" class="preview-img" data-cms-placeholder="1" style="width:100%; height:100%; object-fit:cover; cursor:pointer;">
                    @endif
                </div>
                <div>
                    <div style="display:flex; flex-direction:column; gap:5px;">
                        <input
                            class="dynamic-hidden-file hidden-file-input"
                            type="file"
                            name="{{ $inputName }}"
                            accept="image/*"
                            data-dynamic-image-upload
                            @if(isset($rowIndex)) data-upload-key="{{ 'dyn_' . md5($inputName . $subName) }}" @endif
                            data-file-type="{{ $meta['fileType'] }}"
                            data-min-width="{{ $meta['width'] }}"
                            data-min-height="{{ $meta['height'] }}"
                            data-output-width="{{ $meta['width'] }}"
                            data-output-height="{{ $meta['height'] }}"
                            data-max-size="{{ $meta['maxSize'] }}"
                            data-required-upload="{{ $required ? '1' : '0' }}"
                            data-field-label="{{ $label }}"
                        >
                        <button type="button" class="btn btn-default trigger-crop-btn">選擇檔案</button>
                        <a href="javascript:void(0)" class="dynamic-remove-image-file" style="color:red; text-decoration:none; font-size:14px; margin-top:5px; {{ $fileInfo ? 'display:inline-block;' : 'display:none;' }}"><i class="fas fa-times-circle"></i> 刪除</a>
                    </div>
                    <div style="margin-top:5px;">
                        <p class="file-name-display cms-image-file-name-hidden" style="display:none; font-size:0.9rem; color:#555; margin:0;">未選擇任何檔案</p>
                        <p class="status-msg" style="font-size:0.9rem; color:blue; margin:5px 0 0 0;"></p>
                    </div>
                </div>
            </div>
            <div style="margin-top:5px; display:flex; align-items:center;">
                <span class="table_data" style="flex-shrink:0;">圖片說明：</span>
                <input type="text" name="{{ $inputName }}[_title]" value="{{ $fileInfo['title'] ?? $fileInfo['alt_text'] ?? '' }}" class="table_data dynamic-image-title-input" style="width:300px; padding:4px; border:1px solid #ccc;">
            </div>
        </div>
        @if($meta['dimensionText'] || $meta['maxSize'] || !empty($subField['note']))
            <label class="error dynamic-note">
                * @if($meta['dimensionText'])建議尺寸：{{ $meta['dimensionText'] }} @endif
                @if($meta['maxSize'])(大小限制 {{ $meta['maxSize'] }}MB)@endif
                {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
            </label>
        @endif
    @elseif(in_array($subType, ['file', 'file_upload'], true))
        <div class="dynamic-file-row">
            @if($fileInfo && !empty($fileInfo['url']))
                <a href="{{ $fileInfo['url'] }}" target="_blank" class="dynamic-existing-file">{{ $fileInfo['original_name'] ?? '已上傳檔案' }}</a>
                <input type="hidden" name="{{ $inputName }}[_existing]" value="{{ base64_encode(json_encode($fileInfo, JSON_UNESCAPED_UNICODE)) }}">
            @endif
            <input class="dynamic-hidden-file" type="file" name="{{ $inputName }}" accept="{{ $format }}" data-required-upload="{{ $required ? '1' : '0' }}" data-field-label="{{ $label }}">
            <button type="button" class="btn btn-default dynamic-trigger-file">選擇檔案</button>
            <span class="dynamic-file-name">{{ $fileInfo['original_name'] ?? '未選擇任何檔案' }}</span>
            <input class="dynamic-file-title" type="text" name="{{ $inputName }}[_title]" value="{{ $fileInfo['title'] ?? '' }}" placeholder="檔案說明">
        </div>
        @if($format || $meta['maxSize'] || !empty($subField['note']))
            <label class="error dynamic-note">
                * @if($format)支援格式：{{ str_replace('.', '', $format) }} @endif
                @if($meta['maxSize'])(大小限制 {{ $meta['maxSize'] }}MB)@endif
                {!! !empty($subField['note']) ? ' ' . $subField['note'] : '' !!}
            </label>
        @endif
    @else
        <input class="form-control" type="{{ $subType === 'number' ? 'number' : 'text' }}" name="{{ $inputName }}" value="{{ is_array($subValue) ? '' : $subValue }}" @if($required) required @endif>
    @endif
</div>
