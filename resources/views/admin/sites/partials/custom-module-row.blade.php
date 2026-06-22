@php
    $moduleName = $module['name'] ?? '';
    $moduleSlug = $module['slug'] ?? '';
    $moduleType = $module['type'] ?? 'single';
@endphp

<div class="site-custom-module-row mb-3 p-3 border rounded">
    <div class="row g-2 align-items-center">
        <div class="col-lg-3">
            <input type="text" class="form-control" name="custom_modules[{{ $index }}][name]" value="{{ $moduleName }}" placeholder="模組名稱，例如：部落格">
        </div>
        <div class="col-lg-3">
            <input type="text" class="form-control" name="custom_modules[{{ $index }}][slug]" value="{{ $moduleSlug }}" placeholder="模組代碼，例如：blog">
        </div>
        <div class="col-lg-4">
            <select class="form-control" name="custom_modules[{{ $index }}][type]">
                @foreach($templates as $value => $label)
                    <option value="{{ $value }}" @selected($moduleType === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <button type="button" class="btn btn-danger js-remove-custom-module">
                <i class="fas fa-trash"></i> 刪除
            </button>
        </div>
    </div>
</div>
