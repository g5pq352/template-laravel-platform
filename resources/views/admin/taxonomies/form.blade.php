@extends('admin.partials.shell')

@php
    $formAction = $term ? '編輯' : '新增';
@endphp

@section('title', $taxonomyModel->name . $formAction)
@section('page_title', $taxonomyModel->name . $formAction)

@section('breadcrumb')
    <li><span>{{ $taxonomyModel->name }}</span></li>
    <li><span>{{ $formAction }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" action="{{ $term ? route('admin.taxonomies.update', [$taxonomy, $term]) : route('admin.taxonomies.store', $taxonomy) }}">
    @csrf
    @if($term)
        @method('PUT')
    @endif

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => old('parent_id', $values['parent_id'] ?? null)])) }}">
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
                                <a class="nav-link" id="tab-seo" data-bs-toggle="pill" data-bs-target="#seo" role="tab" aria-controls="seo" aria-selected="false">
                                    <i class="bx bx-cog me-2"></i> SEO設定
                                </a>
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="basic" role="tabpanel" aria-labelledby="tab-basic">
                                    <input type="hidden" name="locale" value="{{ old('locale', $values['locale'] ?? $site->default_locale) }}">

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">上層分類</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-modern" name="parent_id">
                                                <option value="">頂層</option>
                                                @foreach($parentOptions as $option)
                                                    <option value="{{ $option['id'] }}" @selected((int) old('parent_id', $values['parent_id'] ?? 0) === (int) $option['id'])>{{ $option['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">分類名稱 <span class="required">*</span></label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="name" value="{{ old('name', $values['name'] ?? null) }}" required>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">描述</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <textarea class="form-control form-control-modern" name="description" rows="5">{{ old('description', $values['description'] ?? null) }}</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">在網頁顯示</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <select class="form-control form-control-md" name="is_active" id="is_active">
                                                <option value="1" @selected((string) old('is_active', (int) ($values['is_active'] ?? true)) === '1')>顯示</option>
                                                <option value="0" @selected((string) old('is_active', (int) ($values['is_active'] ?? true)) === '0')>不顯示</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="seo" role="tabpanel" aria-labelledby="tab-seo">
                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">網址別名 (slug)</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="slug" value="{{ old('slug', $values['slug'] ?? null) }}">
                                            <div class="text-danger text-2 mt-2">留空則自動從標題產生</div>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">SEO 標題</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <input class="form-control form-control-modern" name="seo_title" value="{{ old('seo_title', $values['seo_title'] ?? null) }}">
                                            <div class="text-danger text-2 mt-2">建議長度：50-60 字元</div>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-lg-5 col-xl-2 control-label text-lg-end pt-2">SEO 描述 (meta description)</label>
                                        <div class="col-lg-7 col-xl-7">
                                            <textarea class="form-control form-control-modern" name="seo_description" rows="4">{{ old('seo_description', $values['seo_description'] ?? null) }}</textarea>
                                            <div class="text-danger text-2 mt-2">建議長度：150-160 字元</div>
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
