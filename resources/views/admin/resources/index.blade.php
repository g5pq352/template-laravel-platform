@extends('admin.partials.shell')

@php
    $moduleLabel = match ($resource) {
        'news' => '最新消息',
        'products' => '產品',
        'contact' => '聯絡我們',
        default => $resourceConfig['label'],
    };
@endphp

@section('title', $resourceConfig['label'])
@section('page_title', $resourceConfig['label'])

@section('breadcrumb')
    <li><span>{{ $moduleLabel }}</span></li>
    <li><span>{{ $moduleLabel }}列表</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        <div class="row align-items-center mb-3">
            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                @if($resourceConfig['show_add_button'] ?? true)
                    <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route("admin.{$resource}.create", array_filter(['term_id' => $termId])) }}">
                        <i class="fas fa-plus-circle"></i> 新增
                    </a>
                @endif

                @if($trash ?? false)
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route("admin.{$resource}.index") }}">
                        <i class="fas fa-list"></i> 返回列表
                    </a>
                @else
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route("admin.{$resource}.index", ['trash' => 1]) }}">
                        <i class="fas fa-trash-alt"></i> 垃圾桶 ({{ $trashCount ?? 0 }})
                    </a>
                @endif
            </div>
        </div>

        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper dataTables_wrapper mt-2">
                    <div class="datatable-header">
                        <form method="get" action="{{ route("admin.{$resource}.index") }}" class="row align-items-center mb-3">
                            @if($trash ?? false)
                                <input type="hidden" name="trash" value="1">
                            @endif

                            <div class="col-8 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                    @if(!empty($taxonomyOptions))
                                        <label class="ws-nowrap me-3 mb-0">Filter By:</label>
                                        <select name="term_id" id="select1" class="form-control select-style-1 filter-by" onchange="this.form.submit()">
                                            <option value="">全部</option>
                                            @foreach($taxonomyOptions as $option)
                                                <option value="{{ $option['id'] }}" @selected((int) $termId === (int) $option['id'])>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <select name="select1" id="select1" class="form-control select-style-1 filter-by" style="display: none;">
                                            <option value="all">all</option>
                                        </select>
                                    @endif
                                </div>
                            </div>

                            <div class="col-4 col-lg-auto ps-lg-1 mb-3 mb-lg-0">
                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                    <label class="ws-nowrap me-3 mb-0">Show:</label>
                                    <select class="form-control select-style-1 results-per-page" name="per_page" onchange="this.form.submit()">
                                        @foreach([12, 24, 36, 100] as $size)
                                            <option value="{{ $size }}" @selected((int) request('per_page', 12) === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-lg-auto ps-lg-1">
                                <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                    <div class="input-group">
                                        <input type="text" class="search-term form-control" name="search" id="search-term" placeholder="Search Category" value="{{ $keyword }}">
                                        <button class="btn btn-default search-button" type="submit"><i class="bx bx-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="table table-ecommerce-simple table-striped mb-0 dataTable no-footer" id="datatable-ecommerce-list" style="min-width: 550px;">
                        <thead>
                            <tr>
                                <th width="3%" class="sorting_disabled"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value=""></th>
                                @foreach($resourceConfig['list_columns'] as $column)
                                    <th class="sorting_disabled" @if(!empty($column['width'])) width="{{ $column['width'] }}" @endif>{{ $column['label'] }}</th>
                                @endforeach
                                <th width="30" class="sorting_disabled">{{ $trash ?? false ? '還原' : '編輯' }}</th>
                                <th width="30" class="sorting_disabled">刪除</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                                <tr>
                                    <td><input type="checkbox" name="selected[]" class="checkbox-style-1 p-relative top-2" value="{{ $item->id }}"></td>
                                    @foreach($resourceConfig['list_columns'] as $column)
                                        <td>@include('admin.resources.partials.cell', ['item' => $item, 'column' => $column, 'resourceConfig' => $resourceConfig])</td>
                                    @endforeach

                                    @if($trash ?? false)
                                        <td>
                                            <form method="post" action="{{ route("admin.{$resource}.restore", $item->id) }}" class="js-resource-restore-form">
                                                @csrf
                                                <button class="btn btn-success" type="submit" title="還原"><i class="fas fa-undo"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" action="{{ route("admin.{$resource}.force-delete", $item->id) }}" class="js-resource-force-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="永久刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @else
                                        <td><a class="btn btn-info" href="{{ route("admin.{$resource}.edit", $item) }}" title="編輯"><i class="fas fa-edit"></i></a></td>
                                        <td>
                                            <form method="post" action="{{ route("admin.{$resource}.destroy", $item) }}" class="js-resource-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($resourceConfig['list_columns']) + 3 }}" class="text-center text-muted cms-table-empty">No data available in table</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <hr class="solid mt-5 opacity-4">
                    <div class="datatable-footer">
                        <div class="row align-items-center justify-content-between mt-3">
                            <div class="col-md-auto order-1 mb-3 mb-lg-0">
                                <div class="d-flex align-items-stretch">
                                    <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">
                                        <select class="form-control select-style-1 bulk-action" name="bulk-action" style="min-width: 170px;">
                                            <option value="" selected>批次操作</option>
                                            @if($trash ?? false)
                                                <option value="restore">批次還原</option>
                                                <option value="force_delete">批次永久刪除</option>
                                            @else
                                                <option value="delete">批次移至垃圾桶</option>
                                                @if(($resourceConfig['strategy'] ?? null) !== 'contact')
                                                    <option value="clone">批次複製</option>
                                                @endif
                                            @endif
                                        </select>
                                        <select class="form-control select-style-1 bulk-action-lang d-none" name="target_lang" style="min-width: 140px;">
                                            <option value="">選擇語系...</option>
                                        </select>
                                        <a href="javascript:void(0);" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3" style="min-width: 90px;">執行</a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-auto text-center order-3 order-lg-2 cms-count">
                                顯示第 {{ $items->firstItem() ?? 0 }} 至 {{ $items->lastItem() ?? 0 }} 項結果，共 {{ $items->total() }} 項
                            </div>

                            @if($items->hasPages())
                                <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                    {{ $items->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        async function postJson(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload || {}),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        }

        async function deleteJson(url, payload = {}) {
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            return response.json();
        }

        function redirectAfterResourceAction(data) {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            window.location.reload();
        }

        document.querySelectorAll('.js-row-sort').forEach(function (select) {
            select.dataset.previousValue = select.value;
            select.addEventListener('change', async function () {
                const previousValue = select.dataset.previousValue;
                select.disabled = true;

                try {
                    await postJson(select.dataset.url, { sort_order: select.value });
                    select.dataset.previousValue = select.value;
                    window.location.reload();
                } catch (error) {
                    select.value = previousValue;
                    console.error(error);
                } finally {
                    select.disabled = false;
                }
            });
        });

        document.querySelectorAll('.js-row-pin').forEach(function (button) {
            button.addEventListener('click', async function () {
                button.disabled = true;

                try {
                    const data = await postJson(button.dataset.url);
                    button.classList.toggle('btn-warning', data.is_pinned);
                    button.classList.toggle('btn-default', !data.is_pinned);
                    button.title = data.is_pinned ? '取消置頂' : '置頂';
                } catch (error) {
                    console.error(error);
                } finally {
                    button.disabled = false;
                }
            });
        });

        document.querySelectorAll('.js-row-status').forEach(function (badge) {
            badge.addEventListener('click', async function () {
                try {
                    const data = await postJson(badge.dataset.url);
                    badge.textContent = data.label;
                    badge.style.backgroundColor = data.color;
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelector('.select-all')?.addEventListener('change', function (event) {
            document.querySelectorAll('input[name="selected[]"]').forEach((checkbox) => checkbox.checked = event.target.checked);
        });

        document.querySelectorAll('.js-resource-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要移至垃圾桶嗎？')) {
                    return;
                }

                try {
                    redirectAfterResourceAction(await deleteJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelectorAll('.js-resource-force-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要永久刪除嗎？此操作無法復原。', {
                    confirmButtonText: '永久刪除',
                    confirmButtonColor: '#dc3545',
                })) {
                    return;
                }

                try {
                    redirectAfterResourceAction(await deleteJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelectorAll('.js-resource-restore-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                try {
                    redirectAfterResourceAction(await postJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelector('.bulk-action-apply')?.addEventListener('click', async function () {
            const action = document.querySelector('.bulk-action')?.value;
            const ids = [...document.querySelectorAll('input[name="selected[]"]:checked')].map((checkbox) => checkbox.value);
            if (!action || ids.length === 0) {
                return;
            }

            if (['delete', 'force_delete'].includes(action)) {
                const confirmed = await cmsConfirm('確定要執行這個批次操作嗎？');
                if (!confirmed) return;
            }

            try {
                const data = await postJson(@json(route("admin.{$resource}.bulk-action")), { action, ids });
                if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                    return;
                }
                window.location.reload();
            } catch (error) {
                console.error(error);
            }
        });
    });
</script>
@endpush
