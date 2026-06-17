@extends('admin.partials.shell')

@php
    $formAction = $menu ? '編輯' : '新增';
    $languageParams = ($languageEnabled ?? false) ? ($languageParams ?? array_filter(['language' => $languageContext['slug'] ?? null])) : [];
@endphp

@section('title', 'CMS 選單管理' . $formAction)
@section('page_title', 'CMS 選單管理' . $formAction)

@section('breadcrumb')
    <li><span>選單管理</span></li>
    <li><span>{{ $formAction }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" action="{{ $menu ? route('admin.menus.update', [$menu, ...$languageParams]) : route('admin.menus.store', $languageParams) }}">
    @csrf
    @if($menu)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.menus.index', array_filter(['location' => old('location', $values['location'] ?? $location), 'parent_id' => old('parent_id', $values['parent_id'] ?? null), ...$languageParams])) }}">
                    <i class="bx bx-left-arrow-alt me-1"></i> 返回
                </a>
                <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" type="submit" id="submitBtn">
                    <i class="bx bx-save me-1"></i> 儲存 (alt+s)
                </button>
            </div>

            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 512px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" role="tablist" aria-orientation="vertical">
                                <a class="nav-link active" id="tab-basic" data-bs-toggle="pill" data-bs-target="#basic" role="tab" aria-controls="basic" aria-selected="true">
                                    <i class="bx bx-cog me-2"></i> 資料設定
                                </a>
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="tab-basic">
                                    <input type="hidden" name="locale" value="{{ old('locale', $values['locale'] ?? $site?->default_locale) }}">

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">選單位置</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="location">
                                                <option value="backend" @selected(old('location', $values['location'] ?? 'backend') === 'backend')>後端選單</option>
                                                <option value="frontend" @selected(old('location', $values['location'] ?? 'backend') === 'frontend')>前端選單</option>
                                                <option value="footer" @selected(old('location', $values['location'] ?? 'backend') === 'footer')>頁尾選單</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">上層選單</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="parent_id">
                                                <option value="">頂層</option>
                                                @foreach($parentOptions as $option)
                                                    <option value="{{ $option['id'] }}" @selected((int) old('parent_id', $values['parent_id'] ?? 0) === (int) $option['id'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">標題 <span class="required">*</span></label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="title" value="{{ old('title', $values['title'] ?? null) }}" required>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">類型</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="type">
                                                <option value="custom" @selected(old('type', $values['type'] ?? 'custom') === 'custom')>自訂連結</option>
                                                <option value="module" @selected(old('type', $values['type'] ?? 'custom') === 'module')>模組</option>
                                                <option value="route" @selected(old('type', $values['type'] ?? 'custom') === 'route')>Laravel Route</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">URL</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="url" value="{{ old('url', $values['url'] ?? null) }}" placeholder="/news 或 https://example.com">
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">Route / 模組代碼</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <div class="row">
                                                <div class="col-md-6 mb-2 mb-md-0">
                                                    <input class="form-control form-control-modern" name="route_name" value="{{ old('route_name', $values['route_name'] ?? null) }}" placeholder="admin.news.index">
                                                </div>
                                                <div class="col-md-6">
                                                    <input class="form-control form-control-modern" name="module_key" value="{{ old('module_key', $values['module_key'] ?? null) }}" placeholder="news / products">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">Icon</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="icon" value="{{ old('icon', $values['icon'] ?? null) }}" placeholder="bx bx-file 或 fa-solid fa-bars">
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">開啟方式</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="target">
                                                <option value="_self" @selected(old('target', $values['target'] ?? '_self') === '_self')>同視窗</option>
                                                <option value="_blank" @selected(old('target', $values['target'] ?? '_self') === '_blank')>新視窗</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row cms-form-row">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">網頁顯示</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="is_active">
                                                <option value="1" @selected((string) old('is_active', (int) ($values['is_active'] ?? true)) === '1')>顯示</option>
                                                <option value="0" @selected((string) old('is_active', (int) ($values['is_active'] ?? true)) === '0')>不顯示</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('keydown', function (event) {
        if (event.altKey && event.key.toLowerCase() === 's') {
            event.preventDefault();
            document.getElementById('submitBtn')?.click();
        }
    });
</script>
@endpush
