@extends('admin.partials.shell')

@php
    $formAction = $content ? '編輯內容' : '新增內容';
    $selectedTypeId = old('content_type_id', $content?->content_type_id ?? ($defaultTypeId ?? null));
@endphp

@section('title', $formAction)
@section('page_title', $formAction)

@section('breadcrumb')
    <li><a href="{{ route('admin.contents.index', array_filter(['content_type_id' => $selectedTypeId])) }}">內容管理</a></li>
    <li><span>{{ $formAction }}</span></li>
@endsection

@section('content')
<form class="ecommerce-form" method="post" action="{{ $content ? route('admin.contents.update', $content) : route('admin.contents.store') }}">
    @csrf
    @if($content)
        @method('PUT')
    @endif

    <div class="row mb-3">
        <div class="col">
            <div class="cms-page-actions">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.contents.index', array_filter(['content_type_id' => $selectedTypeId])) }}">
                    <i class="fas fa-arrow-left"></i> 返回
                </a>
                <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" type="submit">
                    <i class="fas fa-floppy-disk"></i> 儲存 (alt+s)
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 490px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" id="content-tabs" role="tablist" aria-orientation="vertical">
                                <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button" role="tab">
                                    <i class="bx bx-detail me-1"></i> 資料設定
                                </button>
                                <button class="nav-link" id="seo-tab" data-bs-toggle="pill" data-bs-target="#seo" type="button" role="tab">
                                    <i class="bx bx-search-alt me-1"></i> SEO設定
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content" id="content-tabs-panel">
                                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">內容類型 <span class="required">*</span></label>
                                        <div class="col-sm-7">
                                            <select class="form-control" name="content_type_id" required>
                                                @foreach($contentTypes as $type)
                                                    <option value="{{ $type->id }}" @selected((int) $selectedTypeId === (int) $type->id)>
                                                        {{ $type->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">語系 <span class="required">*</span></label>
                                        <div class="col-sm-4">
                                            <input class="form-control" name="locale" value="{{ old('locale', $translation?->locale ?? $site->default_locale) }}" required>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">標題 <span class="required">*</span></label>
                                        <div class="col-sm-7">
                                            <input class="form-control" name="title" value="{{ old('title', $translation?->title) }}" required>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">Slug</label>
                                        <div class="col-sm-7">
                                            <input class="form-control" name="slug" value="{{ old('slug', $translation?->slug) }}">
                                            <div class="text-danger text-2 mt-2">留空則自動依標題產生</div>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">狀態 <span class="required">*</span></label>
                                        <div class="col-sm-4">
                                            <select class="form-control" name="status" required>
                                                @foreach(['draft' => '草稿', 'published' => '顯示', 'scheduled' => '排程', 'hidden' => '不顯示', 'archived' => '封存'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('status', $content?->status ?? 'draft') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">排序</label>
                                        <div class="col-sm-4">
                                            <input class="form-control" type="number" name="sort_order" value="{{ old('sort_order', $content?->sort_order ?? 0) }}">
                                        </div>
                                        <div class="col-sm-5 d-flex align-items-center">
                                            <div class="checkbox-custom checkbox-default">
                                                <input id="is_pinned" type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $content?->is_pinned))>
                                                <label for="is_pinned">置頂</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">摘要</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="summary" rows="4">{{ old('summary', $translation?->summary) }}</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">內容</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control tiny" name="body" rows="12">{{ old('body', $translation?->body) }}</textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="seo" role="tabpanel" aria-labelledby="seo-tab">
                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">SEO 標題</label>
                                        <div class="col-sm-7">
                                            <input class="form-control" name="seo_title" value="{{ old('seo_title', $translation?->seo_title) }}">
                                        </div>
                                    </div>

                                    <div class="form-group row pb-3">
                                        <label class="col-sm-3 control-label text-sm-end pt-2">SEO 描述</label>
                                        <div class="col-sm-8">
                                            <textarea class="form-control" name="seo_description" rows="4">{{ old('seo_description', $translation?->seo_description) }}</textarea>
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
            document.querySelector('.ecommerce-form')?.submit();
        }
    });
</script>
@endpush
