@extends('admin.partials.shell')

@section('title', 'CMS 選單管理')
@section('page_title', 'CMS 選單管理')

@section('breadcrumb')
    <li><span>選單管理</span></li>
    <li><span>{{ $parent ? '後端選單子層列表' : '後端選單列表' }}</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        <div class="row align-items-center mb-3">
            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                @if($parent)
                    <span class="me-3">目前位置：{{ $parent->pathLabel() }}</span>
                    <a href="{{ route('admin.menus.index', array_filter(['location' => $location, 'parent_id' => $parent->parent_id])) }}" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                        <i class="fas fa-arrow-left"></i> 返回上一層
                    </a>
                @else
                    <span class="me-3">目前位置：頂層選單</span>
                @endif

                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.menus.create', array_filter(['location' => $location, 'parent_id' => $parent?->id])) }}">
                    <i class="fas fa-plus-circle"></i> 新增
                </a>

                @if($trash ?? false)
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.menus.index', array_filter(['location' => $location, 'parent_id' => $parent?->id])) }}">
                        <i class="fas fa-list"></i> 返回列表
                    </a>
                @else
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.menus.index', array_filter(['location' => $location, 'parent_id' => $parent?->id, 'trash' => 1])) }}">
                        <i class="fas fa-trash-alt"></i> 垃圾桶 ({{ $trashCount ?? 0 }})
                    </a>
                @endif
            </div>
        </div>

        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper dataTables_wrapper mt-2">
                    <div class="datatable-header">
                        <form method="get" class="row align-items-center mb-3">
                            <input type="hidden" name="location" value="{{ $location }}">
                            @if($parent)
                                <input type="hidden" name="parent_id" value="{{ $parent->id }}">
                            @endif
                            @if($trash ?? false)
                                <input type="hidden" name="trash" value="1">
                            @endif

                            <div class="col-4 col-lg-auto ms-auto ps-lg-1 mb-3 mb-lg-0">
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
                                        <input type="text" class="search-term form-control" name="keyword" value="{{ $keyword }}" placeholder="Search Menu">
                                        <button class="btn btn-default search-button" type="submit"><i class="bx bx-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="table table-ecommerce-simple table-striped mb-0 dataTable no-footer" style="min-width: 760px;">
                        <thead>
                            <tr>
                                <th width="3%" class="sorting_disabled"><input type="checkbox" class="select-all checkbox-style-1 p-relative top-2"></th>
                                <th width="74" class="sorting_disabled">排序</th>
                                <th width="260" class="sorting_disabled">標題</th>
                                <th width="140" class="sorting_disabled">標籤屬性</th>
                                <th class="sorting_disabled">連結</th>
                                <th width="70" class="sorting_disabled">狀態</th>
                                <th width="60" class="sorting_disabled">下一層</th>
                                <th width="30" class="sorting_disabled">{{ $trash ?? false ? '還原' : '編輯' }}</th>
                                <th width="30" class="sorting_disabled">刪除</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($menus as $menu)
                                <tr>
                                    <td><input type="checkbox" name="selected[]" class="row-check checkbox-style-1 p-relative top-2" value="{{ $menu->id }}"></td>
                                    <td>
                                        <select class="form-control-sm js-menu-sort" style="width: 55px;" data-url="{{ route('admin.menus.sort', $menu) }}">
                                            @for($i = 1; $i <= max(1, $sortOptionCount ?? $menus->total()); $i++)
                                                <option value="{{ $i }}" @selected((int) $menu->sort_order === $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </td>
                                    <td><a href="{{ route('admin.menus.edit', $menu) }}">{{ $menu->title }}</a></td>
                                    <td>{{ $menu->module_key ?: $menu->type }}</td>
                                    <td>{{ $menu->url ?: $menu->route_name }}</td>
                                    <td>
                                        <span class="btn js-menu-status" data-url="{{ route('admin.menus.toggle-status', $menu) }}" style="background-color: {{ $menu->is_active ? '#28a745' : '#dc3545' }}; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;">
                                            {{ $menu->is_active ? '顯示' : '不顯示' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.menus.index', ['location' => $location, 'parent_id' => $menu->id]) }}" class="btn btn-primary" title="下一層">
                                            <i class="fas fa-level-down-alt"></i>
                                        </a>
                                    </td>

                                    @if($trash ?? false)
                                        <td>
                                            <form method="post" action="{{ route('admin.menus.restore', $menu->id) }}" class="js-menu-restore-form">
                                                @csrf
                                                <button class="btn btn-success" type="submit" title="還原"><i class="fas fa-undo"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" action="{{ route('admin.menus.force-delete', $menu->id) }}" class="js-menu-force-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="永久刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @else
                                        <td><a class="btn btn-info" href="{{ route('admin.menus.edit', $menu) }}" title="編輯"><i class="fas fa-edit"></i></a></td>
                                        <td>
                                            <form method="post" action="{{ route('admin.menus.destroy', $menu) }}" class="js-menu-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted cms-table-empty">No data available in table</td>
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
                                        <select class="form-control select-style-1 bulk-action" style="min-width: 170px;">
                                            <option value="" selected>批次操作</option>
                                            @if($trash ?? false)
                                                <option value="restore">批次還原</option>
                                                <option value="force_delete">批次永久刪除</option>
                                            @else
                                                <option value="delete">批次移至垃圾桶</option>
                                            @endif
                                        </select>
                                        <a href="javascript:void(0);" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3" style="min-width: 90px;">執行</a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-auto text-center order-3 order-lg-2 cms-count">
                                顯示第 {{ $menus->firstItem() ?? 0 }} 至 {{ $menus->lastItem() ?? 0 }} 項結果，共 {{ $menus->total() }} 項
                            </div>

                            @if($menus->hasPages())
                                <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                    {{ $menus->links() }}
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

            if (!response.ok) throw new Error('Request failed');
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

            if (!response.ok) throw new Error('Request failed');
            return response.json();
        }

        function redirectAfterMenuAction(data) {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            window.location.reload();
        }

        document.querySelector('.select-all')?.addEventListener('change', function (event) {
            document.querySelectorAll('.row-check').forEach((checkbox) => checkbox.checked = event.target.checked);
        });

        document.querySelectorAll('.js-menu-sort').forEach(function (select) {
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

        document.querySelectorAll('.js-menu-status').forEach(function (badge) {
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

        document.querySelectorAll('.js-menu-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要移至垃圾桶嗎？')) {
                    return;
                }

                try {
                    redirectAfterMenuAction(await deleteJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelectorAll('.js-menu-force-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要永久刪除嗎？此操作無法復原。', {
                    confirmButtonText: '永久刪除',
                    confirmButtonColor: '#dc3545',
                })) {
                    return;
                }

                try {
                    redirectAfterMenuAction(await deleteJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelectorAll('.js-menu-restore-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                try {
                    redirectAfterMenuAction(await postJson(form.action));
                } catch (error) {
                    console.error(error);
                }
            });
        });

        document.querySelector('.bulk-action-apply')?.addEventListener('click', async function () {
            const action = document.querySelector('.bulk-action')?.value;
            const ids = [...document.querySelectorAll('.row-check:checked')].map((checkbox) => checkbox.value);
            if (!action || ids.length === 0) {
                return;
            }

            if (['delete', 'force_delete'].includes(action)) {
                const confirmed = await cmsConfirm('確定要執行這個批次操作嗎？');
                if (!confirmed) return;
            }

            try {
                const data = await postJson(@json(route('admin.menus.bulk-action')), { action, ids });
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
