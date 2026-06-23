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
    $enabledModules = old('enabled_modules', $values['enabled_modules'] ?? []);
    $customModules = old('custom_modules', $values['custom_modules'] ?? []);
    if ($customModules === []) {
        $customModules = [['name' => '', 'slug' => '', 'type' => 'single']];
    }
    $standardModules = [
        'news' => '最新消息',
        'products' => '產品管理',
        'contact' => '聯絡我們',
    ];
    $customModuleTemplates = [
        'single' => '一般內容模組（單層分類）',
        'multi' => '進階內容模組（多層分類）',
        'contactus' => '純表單模組（不含分類與標籤）',
        'info' => '單頁設定模組',
        'list_only' => '純列表模組',
    ];
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

    <div class="row">
        <div class="col">
            <div class="cms-page-actions">
                <a href="{{ route('admin.sites.index') }}" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                    <i class="fas fa-arrow-left"></i> 返回
                </a>
                <button type="submit" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                    <i class="fas fa-save"></i> 儲存 (alt+s)
                </button>
                @if($isEdit)
                    <button type="button" class="btn btn-dark btn-md font-weight-semibold btn-py-2 px-4 js-site-git-push" data-url="{{ route('admin.sites.git-push', $managedSite) }}">
                        <i class="fab fa-gitlab"></i> Git Push
                    </button>
                @endif
            </div>
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

    <section class="card card-modern card-big-info">
        <div class="card-body">
            <div class="tabs-modern row" style="min-height: 490px;">
                <div class="col-lg-2-5 col-xl-1-5">
                    <div class="nav flex-column" id="site-tab" role="tablist" aria-orientation="vertical">
                        <a class="nav-link active" id="site-main-tab" data-bs-toggle="pill" data-bs-target="#site-main" role="tab" aria-controls="site-main" aria-selected="true">
                            <i class="fas fa-cog me-2"></i> 資料設定
                        </a>
                        <a class="nav-link" id="site-domains-tab" data-bs-toggle="pill" data-bs-target="#site-domains" role="tab" aria-controls="site-domains" aria-selected="false">
                            <i class="fas fa-globe me-2"></i> 網域設定
                        </a>
                        <a class="nav-link" id="site-modules-tab" data-bs-toggle="pill" data-bs-target="#site-modules" role="tab" aria-controls="site-modules" aria-selected="false">
                            <i class="fas fa-puzzle-piece me-2"></i> 模組功能
                        </a>
                        <a class="nav-link" id="site-platform-tab" data-bs-toggle="pill" data-bs-target="#site-platform" role="tab" aria-controls="site-platform" aria-selected="false">
                            <i class="fas fa-folder-open me-2"></i> 站點架構
                        </a>
                        <a class="nav-link" id="site-deployment-tab" data-bs-toggle="pill" data-bs-target="#site-deployment" role="tab" aria-controls="site-deployment" aria-selected="false">
                            <i class="fas fa-rocket me-2"></i> 上線資訊
                        </a>
                    </div>
                </div>

                <div class="col-lg-3-5 col-xl-4-5">
                    <div class="tab-content" id="site-tab-content">
                        <div id="site-main" class="tab-pane fade show active" role="tabpanel" aria-labelledby="site-main-tab">
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

                        <div id="site-domains" class="tab-pane fade" role="tabpanel" aria-labelledby="site-domains-tab">
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

                        <div id="site-modules" class="tab-pane fade" role="tabpanel" aria-labelledby="site-modules-tab">
                            <div class="form-group row align-items-start cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">標準模組</label>
                                <div class="col-lg-8">
                                    @foreach($standardModules as $moduleValue => $moduleLabel)
                                        <label class="me-4 mb-2 font-weight-semibold">
                                            <input type="checkbox" name="enabled_modules[]" value="{{ $moduleValue }}" @checked(in_array($moduleValue, $enabledModules, true))>
                                            {{ $moduleLabel }}
                                        </label>
                                    @endforeach
                                    <div class="text-danger text-2 mt-2">勾選後代表該站啟用標準後台模組；實際欄位仍由該站 *Set.php 控制。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-start cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">自訂模組清單</label>
                                <div class="col-lg-8">
                                    <div class="site-custom-module-list">
                                        @foreach($customModules as $index => $customModule)
                                            @include('admin.sites.partials.custom-module-row', [
                                                'index' => $index,
                                                'module' => $customModule,
                                                'templates' => $customModuleTemplates,
                                            ])
                                        @endforeach
                                    </div>

                                    <button type="button" class="btn btn-light mt-3 js-add-custom-module">
                                        <i class="fas fa-plus-circle text-success"></i> 新增自訂模組
                                    </button>
                                    <div class="text-danger text-2 mt-2">自訂模組只記錄站台需求與範本類型，實際功能以該站專屬 *Set.php 實作。</div>
                                </div>
                            </div>
                        </div>

                        <div id="site-platform" class="tab-pane fade" role="tabpanel" aria-labelledby="site-platform-tab">
                            @if($isEdit)
                                <div class="form-group row align-items-start cms-form-row">
                                    <label class="col-lg-3 control-label text-lg-end mb-0">後台登入資訊</label>
                                    <div class="col-lg-8">
                                        <div class="p-3 border rounded bg-light site-admin-access-box">
                                            <div class="mb-3">
                                                <label class="font-weight-semibold mb-1">後台網址</label>
                                                <div class="input-group">
                                                    <input type="text" name="admin_access_url" class="form-control" value="{{ old('admin_access_url', $values['admin_access_url'] ?? '') }}" placeholder="https://example.com.tw/cms">
                                                    @if(!empty($adminAccess['url']))
                                                        <a class="btn btn-light" href="{{ $adminAccess['url'] }}" target="_blank" rel="noopener">開啟</a>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="font-weight-semibold mb-1">帳號</label>
                                                <input type="text" name="admin_access_username" class="form-control" value="{{ old('admin_access_username', $values['admin_access_username'] ?? 'admin') }}" placeholder="admin">
                                            </div>
                                            <div class="mb-2">
                                                <label class="font-weight-semibold mb-1">密碼</label>
                                                <input type="text" name="admin_access_password" class="form-control" value="{{ old('admin_access_password', $adminAccess['password'] ?? '') }}" autocomplete="new-password" placeholder="留空表示不變更">
                                            </div>
                                            @if(!empty($adminAccess['generated_at']))
                                                <div class="text-muted text-2">建立時間：{{ $adminAccess['generated_at'] }}</div>
                                            @endif
                                            <div class="text-danger text-2 mt-2">新增站台時會自動產生，儲存後仍可手動修改。</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">Set 設定檔目錄</label>
                                <div class="col-lg-8">
                                    <input type="text" name="cms_set_path" class="form-control" value="{{ old('cms_set_path', $values['cms_set_path'] ?? '') }}" placeholder="自動產生：站台代號\cms\set" readonly>
                                    <div class="text-danger text-2 mt-2">自動使用站台代號產生；後端客製只需要修改這個目錄內的 *Set.php。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">Next 範本目錄</label>
                                <div class="col-lg-8">
                                    <input type="text" name="frontend_template_path" class="form-control" value="{{ old('frontend_template_path', $values['frontend_template_path'] ?? '') }}" placeholder="自動使用 template-next-platform" readonly>
                                    <div class="text-danger text-2 mt-2">新增站台時自動複製 template-next-platform。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">前端 Git 專案路徑</label>
                                <div class="col-lg-8">
                                    <input type="text" name="repository_path" class="form-control" value="{{ old('repository_path', $values['repository_path'] ?? '') }}" placeholder="自動產生：站台代號" readonly>
                                    <div class="text-danger text-2 mt-2">新增站台時會產生這個實體 Next 專案；留空自動建立為「站台代號」。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">Git 主線分支</label>
                                <div class="col-lg-8">
                                    <input type="text" class="form-control" value="main" readonly>
                                    <div class="text-danger text-2 mt-2">Git remote 固定使用 origin；專案名稱與 Repository URL 才使用站台代號。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">資料庫連線名稱</label>
                                <div class="col-lg-7">
                                    <input type="text" name="db_connection" class="form-control" value="{{ old('db_connection', $values['db_connection'] ?? '') }}" placeholder="site_a">
                                    <div class="text-danger text-2 mt-2">留空會直接使用站台代號，例如 site-a。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">DB Host / Port</label>
                                <div class="col-lg-5">
                                    <input type="text" name="db_host" class="form-control" value="{{ old('db_host', $values['db_host'] ?? '') }}" placeholder="127.0.0.1">
                                </div>
                                <div class="col-lg-2">
                                    <input type="text" name="db_port" class="form-control" value="{{ old('db_port', $values['db_port'] ?? '3306') }}" placeholder="3306">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">資料庫名稱</label>
                                <div class="col-lg-7">
                                    <input type="text" name="db_database" class="form-control" value="{{ old('db_database', $values['db_database'] ?? '') }}">
                                    <div class="text-danger text-2 mt-2">留空會直接使用站台代號，例如 site-a。</div>
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">資料庫帳號</label>
                                <div class="col-lg-7">
                                    <input type="text" name="db_username" class="form-control" value="{{ old('db_username', $values['db_username'] ?? '') }}">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">資料庫密碼</label>
                                <div class="col-lg-7">
                                    <input type="password" name="db_password" class="form-control" value="{{ old('db_password', $values['db_password'] ?? '') }}" autocomplete="new-password" placeholder="{{ $isEdit ? '留空表示不變更' : '' }}">
                                </div>
                            </div>

                        </div>

                        <div id="site-deployment" class="tab-pane fade" role="tabpanel" aria-labelledby="site-deployment-tab">
                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">正式域名</label>
                                <div class="col-lg-7">
                                    <input type="text" name="production_domain" class="form-control" value="{{ old('production_domain', $values['production_domain'] ?? '') }}" placeholder="example.com.tw">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">前台網址</label>
                                <div class="col-lg-7">
                                    <input type="text" name="frontend_url" class="form-control" value="{{ old('frontend_url', $values['frontend_url'] ?? '') }}" placeholder="https://example.com.tw">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">後台網址</label>
                                <div class="col-lg-7">
                                    <input type="text" name="admin_url" class="form-control" value="{{ old('admin_url', $values['admin_url'] ?? '') }}" placeholder="https://example.com.tw/cms">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">GitLab Repo URL</label>
                                <div class="col-lg-7">
                                    <input type="text" name="git_repository_url" class="form-control" value="{{ old('git_repository_url', $values['git_repository_url'] ?? '') }}" placeholder="可留空，由平台設定自動產生">
                                    <div class="text-danger text-2 mt-2">若平台設定 CMS_GIT_REPOSITORY_BASE_URL，留空會自動使用「base/站台代號.git」。</div>
                                </div>
                            </div>

                            @if($isEdit)
                                <div class="form-group row align-items-start cms-form-row">
                                    <label class="col-lg-3 control-label text-lg-end mb-0">Git Push 狀態</label>
                                    <div class="col-lg-8">
                                        @php
                                            $gitStatus = $values['deployment']['git_push_status'] ?? null;
                                            $gitPushedAt = $values['deployment']['git_pushed_at'] ?? null;
                                            $gitMessage = $values['deployment']['git_push_message'] ?? null;
                                        @endphp
                                        <div class="p-3 border rounded bg-light">
                                            <div class="mb-2">
                                                <span class="font-weight-semibold">狀態：</span>
                                                @if($gitStatus === 'success')
                                                    <span class="badge badge-success">成功</span>
                                                @elseif($gitStatus === 'failed')
                                                    <span class="badge badge-danger">失敗</span>
                                                @else
                                                    <span class="text-muted">尚未推送</span>
                                                @endif
                                            </div>
                                            @if($gitPushedAt)
                                                <div class="mb-2"><span class="font-weight-semibold">最後推送：</span>{{ $gitPushedAt }}</div>
                                            @endif
                                            @if($gitMessage)
                                                <div class="text-muted text-2">{{ $gitMessage }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">初始化完成時間</label>
                                <div class="col-lg-7">
                                    <input type="text" name="initialized_at" class="form-control" value="{{ old('initialized_at', $values['initialized_at'] ?? '') }}">
                                </div>
                            </div>

                            <div class="form-group row align-items-center cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end mb-0">域名綁定完成時間</label>
                                <div class="col-lg-7">
                                    <input type="text" name="domain_bound_at" class="form-control" value="{{ old('domain_bound_at', $values['domain_bound_at'] ?? '') }}">
                                </div>
                            </div>

                            <div class="form-group row cms-form-row">
                                <label class="col-lg-3 control-label text-lg-end pt-2 mt-1">備註 / 開發筆記</label>
                                <div class="col-lg-7">
                                    <textarea name="deployment_notes" class="form-control" rows="5">{{ old('deployment_notes', $values['deployment_notes'] ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</form>

<template id="domain-row-template">
    @include('admin.sites.partials.domain-row', ['index' => '__INDEX__', 'domain' => ['domain' => '', 'is_primary' => false, 'force_https' => false]])
</template>
<template id="custom-module-row-template">
    @include('admin.sites.partials.custom-module-row', [
        'index' => '__INDEX__',
        'module' => ['name' => '', 'slug' => '', 'type' => 'single'],
        'templates' => $customModuleTemplates,
    ])
</template>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const list = document.querySelector('.site-domain-list');
        const template = document.getElementById('domain-row-template');
        const moduleList = document.querySelector('.site-custom-module-list');
        const moduleTemplate = document.getElementById('custom-module-row-template');
        let nextIndex = {{ count($domains) }};
        let nextModuleIndex = {{ count($customModules) }};
        const isEdit = @json($isEdit);
        const slugInput = document.querySelector('input[name="slug"]');
        const dbConnectionInput = document.querySelector('input[name="db_connection"]');
        const dbDatabaseInput = document.querySelector('input[name="db_database"]');
        let lastAutoDbName = '';

        function databaseNameFromSlug(slug) {
            return String(slug || '')
                .toLowerCase()
                .trim() || 'site';
        }

        function syncSlugDefaults() {
            if (isEdit || !slugInput) return;

            const nextDbName = databaseNameFromSlug(slugInput.value);
            [dbConnectionInput, dbDatabaseInput].forEach(function (input) {
                if (!input) return;
                if (input.value === '' || input.value === lastAutoDbName) {
                    input.value = nextDbName;
                }
            });
            lastAutoDbName = nextDbName;
        }

        slugInput?.addEventListener('input', syncSlugDefaults);
        syncSlugDefaults();

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

        document.querySelector('.js-add-custom-module')?.addEventListener('click', function () {
            const html = moduleTemplate.innerHTML.replaceAll('__INDEX__', String(nextModuleIndex++));
            moduleList.insertAdjacentHTML('beforeend', html);
        });

        moduleList?.addEventListener('click', function (event) {
            const remove = event.target.closest('.js-remove-custom-module');
            if (!remove) return;

            remove.closest('.site-custom-module-row')?.remove();
        });

        document.querySelector('.js-site-git-push')?.addEventListener('click', async function () {
            if (!await cmsConfirm('確定要將這個站台專案 Git Push 到 GitLab？')) return;

            const form = document.createElement('form');
            form.method = 'post';
            form.action = this.dataset.url;
            form.className = 'd-none';

            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = @json(csrf_token());
            form.appendChild(token);

            document.body.appendChild(form);
            form.submit();
        });
    });
</script>
@endpush
