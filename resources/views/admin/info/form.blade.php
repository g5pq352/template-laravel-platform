@extends('admin.partials.shell')

@section('title', $moduleConfig['label'])
@section('page_title', $moduleConfig['label'])

@section('breadcrumb')
    <li><span>{{ $moduleConfig['label'] }}</span></li>
@endsection

@php
    $languageParams = ($languageEnabled ?? false) ? ($languageParams ?? array_filter(['language' => $languageContext['slug'] ?? null])) : [];
@endphp

@section('content')
<form class="ecommerce-form" method="post" enctype="multipart/form-data" action="{{ route('admin.info.update', [$module, ...$languageParams]) }}">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col">
            <div class="datatable-header">
                <div class="row align-items-center mb-3">
                    <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                        <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4 btn-save" type="submit">
                            <i class="fas fa-floppy-disk"></i> 儲存 (alt+s)
                        </button>
                    </div>

                    @if(($languageEnabled ?? false) && ($languageContext['languages'] ?? collect())->count() > 1)
                        <div class="col-12 col-lg-auto ms-auto">
                            <ul class="nav nav-pills nav-pills-primary justify-content-lg-end">
                                @foreach($languageContext['languages'] as $language)
                                    <li class="nav-item">
                                        <a @class(['nav-link py-1 px-3', 'active' => ($languageContext['slug'] ?? null) === $language->slug])
                                           href="{{ route('admin.info.edit', [$module, 'language' => $language->slug]) }}">
                                            {{ $language->name }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <section class="card card-modern card-big-info">
                <div class="card-body">
                    <div class="tabs-modern row" style="min-height: 490px;">
                        <div class="col-lg-2-5 col-xl-1-5">
                            <div class="nav flex-column" id="info-tabs" role="tablist" aria-orientation="vertical">
                                @foreach($moduleConfig['sections'] as $sectionKey => $section)
                                    <a class="nav-link @if($loop->first) active @endif" id="tab-{{ $sectionKey }}" data-bs-toggle="pill" data-bs-target="#pane-{{ $sectionKey }}" role="tab" aria-controls="pane-{{ $sectionKey }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                        <i class="bx bx-cog me-2"></i> {{ $section['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-lg-3-5 col-xl-4-5">
                            <div class="tab-content" id="info-tabs-panel">
                                @foreach($moduleConfig['sections'] as $sectionKey => $section)
                                    <div class="tab-pane fade @if($loop->first) show active @endif" id="pane-{{ $sectionKey }}" role="tabpanel" aria-labelledby="tab-{{ $sectionKey }}">
                                        @foreach($section['fields'] as $field)
                                            @include('admin.resources.partials.field', [
                                                'field' => $field,
                                                'value' => old($field['name'], $values[$field['name']] ?? null),
                                                'mediaByRole' => $mediaByRole,
                                                'statusOptions' => $field['options'] ?? $statusOptions,
                                                'taxonomyOptions' => $taxonomyOptions,
                                                'selectedTermIds' => $selectedTermIds,
                                            ])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            @if(($languageEnabled ?? false) && ($languageContext['languages'] ?? collect())->count() > 1)
                <div class="datatable-footer">
                    <div class="row align-items-center justify-content-between mt-3">
                        <div class="col-md-auto order-1 mb-3 mb-lg-0">
                            <div class="d-flex align-items-stretch">
                                <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">
                                    <select class="form-control select-style-1 info-copy-action" name="info-copy-action" style="min-width: 170px;">
                                        <option value="" selected>批次操作</option>
                                        <option value="clone">複製到語系</option>
                                    </select>
                                    <select class="form-control select-style-1 info-copy-language d-none" name="info-copy-language" style="min-width: 170px;">
                                        <option value="">選擇語系...</option>
                                        @foreach($languageContext['languages'] as $language)
                                            @continue(($languageContext['slug'] ?? null) === $language->slug)
                                            <option value="{{ $language->slug }}">{{ $language->name }} ({{ $language->slug }})</option>
                                        @endforeach
                                    </select>
                                    <a href="javascript:void(0);" class="info-copy-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3" style="min-width: 90px;">執行</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-auto text-center order-3 order-lg-2">
                            <div class="results-info-wrapper"></div>
                        </div>
                        <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                            <div class="pagination-wrapper"></div>
                        </div>
                    </div>
                </div>
            @endif
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

    window.deleteImageItem = window.deleteImageItem || async function (mediaId) {
        if (!await window.cmsConfirm('確定要刪除此圖片？')) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_file[]';
        input.value = mediaId;
        document.querySelector('.ecommerce-form')?.appendChild(input);
        document.getElementById('img_item_' + mediaId)?.remove();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const action = document.querySelector('.info-copy-action');
        const language = document.querySelector('.info-copy-language');
        const apply = document.querySelector('.info-copy-apply');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        action?.addEventListener('change', function () {
            language?.classList.toggle('d-none', action.value !== 'clone');
            if (action.value !== 'clone' && language) {
                language.value = '';
            }
        });

        async function copyInfo(overwrite = false) {
            const response = await fetch(@json(route('admin.info.copy-language', [$module, ...$languageParams])), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    target_language: language?.value || '',
                    overwrite: overwrite ? 1 : 0,
                }),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(data.message || '複製失敗');
                error.data = data;
                throw error;
            }

            return data;
        }

        apply?.addEventListener('click', async function () {
            if (action?.value !== 'clone') {
                await Swal.fire('提示', '請選擇批次操作', 'info');
                return;
            }

            if (!language?.value) {
                await Swal.fire('錯誤', '請選擇目標語系', 'error');
                return;
            }

            const result = await Swal.fire({
                title: '確認複製',
                html: `確定要將當前語系的資料複製到 <strong>${language.options[language.selectedIndex]?.text || ''}</strong> 嗎？`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '確定複製',
                cancelButtonText: '取消',
                input: 'checkbox',
                inputPlaceholder: '覆蓋已存在的資料',
            });

            if (!result.isConfirmed) {
                return;
            }

            Swal.fire({
                title: '處理中...',
                text: '正在複製資料...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading(),
            });

            try {
                const data = await copyInfo(Boolean(result.value));
                await Swal.fire('複製成功', data.message || '資料已複製', 'success');
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                }
            } catch (error) {
                if (error.data?.needs_overwrite) {
                    await Swal.fire('複製失敗', error.data.message || error.message, 'error');
                    return;
                }

                await Swal.fire('複製失敗', error.message || '發生未知錯誤', 'error');
            }
        });
    });
</script>
@endpush
