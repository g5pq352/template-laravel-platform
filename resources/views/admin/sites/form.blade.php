@extends('admin.partials.shell')

@php
    $isEdit = (bool) $managedSite;
    $action = $isEdit ? route('admin.sites.update', $managedSite) : route('admin.sites.store');
    $domains = old('domains', $values['domains'] ?? []);
    if ($domains === []) {
        $domains = [['domain' => '', 'is_primary' => true, 'force_https' => false]];
    }
    $primaryDomainIndex = old('primary_domain_index');
    if ($primaryDomainIndex !== null) {
        foreach ($domains as $domainIndex => $domain) {
            $domains[$domainIndex]['is_primary'] = (string) $domainIndex === (string) $primaryDomainIndex;
        }
    }
@endphp

@section('title', $isEdit ? '站台編輯' : '站台新增')
@section('page_title', $isEdit ? '站台編輯' : '站台新增')

@section('breadcrumb')
    <li><span>系統管理</span></li>
    <li><a href="{{ route('admin.sites.index') }}">多站管理</a></li>
    <li><span>{{ $isEdit ? '編輯' : '新增' }}</span></li>
@endsection

@section('content')
<form method="post" action="{{ $action }}" class="ecommerce-form action-buttons-fixed">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="row mb-4">
        <div class="col">
            <a href="{{ route('admin.sites.index') }}" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                <i class="fas fa-arrow-left"></i> 返回
            </a>
            <button type="submit" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                <i class="fas fa-save"></i> 儲存 (alt+s)
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>資料未儲存，請檢查欄位。</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-modern">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-3 p-0">
                    <div class="tabs-navigation">
                        <ul class="nav nav-tabs flex-column" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#site-main" role="tab">
                                    <i class="fas fa-cog me-2"></i> 資料設定
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#site-domains" role="tab">
                                    <i class="fas fa-globe me-2"></i> 網域設定
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#site-api" role="tab">
                                    <i class="fas fa-code me-2"></i> API 設定
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-9 p-0">
                    <div class="tab-content">
                        <div id="site-main" class="tab-pane active">
                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">租戶 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <select name="tenant_id" class="form-control">
                                        @foreach($tenants as $tenant)
                                            <option value="{{ $tenant->id }}" @selected((int) old('tenant_id', $values['tenant_id'] ?? null) === (int) $tenant->id)>{{ $tenant->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">站台名稱 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $values['name'] ?? '') }}" required>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">站台代號 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $values['slug'] ?? '') }}" required>
                                    <div class="text-danger text-2 mt-2">用於 API site 參數，例如 site=main-site。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">狀態 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <select name="status" class="form-control">
                                        <option value="active" @selected(old('status', $values['status'] ?? 'active') === 'active')>啟用</option>
                                        <option value="inactive" @selected(old('status', $values['status'] ?? 'active') === 'inactive')>停用</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">預設語系 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <input type="text" name="default_locale" class="form-control" value="{{ old('default_locale', $values['default_locale'] ?? 'zh-Hant-TW') }}" required>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">時區 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <input type="text" name="timezone" class="form-control" value="{{ old('timezone', $values['timezone'] ?? 'Asia/Taipei') }}" required>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">幣別 <span class="required">*</span></label>
                                <div class="col-lg-7">
                                    <input type="text" name="currency_code" class="form-control" value="{{ old('currency_code', $values['currency_code'] ?? 'TWD') }}" maxlength="3" required>
                                </div>
                            </div>
                        </div>

                        <div id="site-domains" class="tab-pane">
                            <div class="form-group row align-items-start cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">網域</label>
                                <div class="col-lg-8">
                                    <div class="site-domain-list">
                                        @foreach($domains as $index => $domain)
                                            @include('admin.sites.partials.domain-row', ['index' => $index, 'domain' => $domain])
                                        @endforeach
                                    </div>

                                    <button type="button" class="btn btn-light mt-3 js-add-domain">
                                        <i class="fas fa-plus-circle text-success"></i> 新增網域
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="site-api" class="tab-pane">
                            <div class="form-group row align-items-start cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">允許前端來源</label>
                                <div class="col-lg-7">
                                    <textarea name="api_allowed_origins" class="form-control" rows="6" placeholder="https://example.com">{{ old('api_allowed_origins', $values['api_allowed_origins'] ?? '') }}</textarea>
                                    <div class="text-danger text-2 mt-2">一行一個來源，保留給 API / CORS 白名單使用。</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<template id="domain-row-template">
    @include('admin.sites.partials.domain-row', ['index' => '__INDEX__', 'domain' => ['domain' => '', 'is_primary' => false, 'force_https' => false]])
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.querySelector('.site-domain-list');
        const template = document.getElementById('domain-row-template');
        let nextIndex = {{ count($domains) }};

        document.querySelector('.js-add-domain')?.addEventListener('click', function () {
            const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
            list.insertAdjacentHTML('beforeend', html);
        });

        list?.addEventListener('click', function (event) {
            const remove = event.target.closest('.js-remove-domain');
            if (!remove) return;

            const row = remove.closest('.site-domain-row');
            row?.remove();

            if (!list.querySelector('input[type="radio"]:checked')) {
                list.querySelector('input[type="radio"]')?.click();
            }
        });
    });
</script>
@endpush
