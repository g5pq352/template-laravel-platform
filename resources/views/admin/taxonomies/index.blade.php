@extends('admin.partials.shell')

@php
    $useHierarchy = $taxonomyModel->is_hierarchical ?? true;
    $termNameColumn = collect($taxonomyConfig['list_columns'] ?? [])->firstWhere('key', 'name');
    $termNameLabel = $termNameColumn['label'] ?? '分類名稱';
    $listTitle = $taxonomyConfig['listPage']['title'] ?? '分類列表';
@endphp

@section('title', $taxonomyModel->name)
@section('page_title', $taxonomyModel->name)

@section('breadcrumb')
    <li><span>{{ $taxonomyModel->name }}</span></li>
    <li><span>{{ $listTitle }}</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        <div class="row align-items-center mb-3">
            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                @if($useHierarchy && $parent)
                    <span class="me-3">目前位置：{{ $parent->pathLabel() }}</span>
                    <a href="{{ route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $parent->parent_id])) }}" class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4">
                        <i class="fas fa-arrow-left"></i> 返回上一層
                    </a>
                @endif

                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.taxonomies.create', array_filter([$taxonomy, 'parent_id' => $parent?->id])) }}">
                    <i class="fas fa-plus-circle"></i> 新增
                </a>

                @if($trash ?? false)
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $parent?->id])) }}">
                        <i class="fas fa-list"></i> 返回列表
                    </a>
                @else
                    <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.taxonomies.index', array_filter([$taxonomy, 'parent_id' => $parent?->id, 'trash' => 1])) }}">
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
                            @if($trash ?? false)
                                <input type="hidden" name="trash" value="1">
                            @endif

                            @if($parent)
                                <input type="hidden" name="parent_id" value="{{ $parent->id }}">
                            @endif
                            <div class="col-8 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0"></div>

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
                                        <input type="text" class="search-term form-control" name="search" id="search-term" placeholder="Search Category" value="{{ request('search') }}">
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
                                <th width="74" class="sorting_disabled">排序</th>
                                <th width="142" class="sorting_disabled">建立日期</th>
                                <th width="400" class="sorting_disabled">{{ $termNameLabel }}</th>
                                @if($useHierarchy)
                                    <th width="60" class="sorting_disabled">下一層</th>
                                @endif
                                <th width="60" class="sorting_disabled">狀態</th>
                                <th width="30" class="sorting_disabled">{{ $trash ?? false ? '還原' : '編輯' }}</th>
                                <th width="30" class="sorting_disabled">刪除</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($terms as $term)
                                <tr>
                                    <td><input type="checkbox" name="selected[]" class="checkbox-style-1 p-relative top-2" value="{{ $term->id }}"></td>
                                    <td>
                                        <select class="form-control-sm js-taxonomy-sort" style="width: 55px;" data-url="{{ route('admin.taxonomies.sort', [$taxonomy, $term]) }}">
                                            @for($i = 1; $i <= max(1, $sortOptionCount ?? $terms->total()); $i++)
                                                <option value="{{ $i }}" @selected((int) $term->sort_order === $i)>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </td>
                                    <td>{{ optional($term->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td>
                                        <a href="{{ route('admin.taxonomies.edit', [$taxonomy, $term]) }}">{{ $term->name }}</a>
                                    </td>
                                    @if($useHierarchy)
                                        <td>
                                            <a href="{{ route('admin.taxonomies.index', [$taxonomy, 'parent_id' => $term->id]) }}" class="btn btn-primary" title="下一層">
                                                <i class="fas fa-level-down-alt"></i>
                                            </a>
                                        </td>
                                    @endif
                                    <td>
                                        <span class="btn js-taxonomy-status" data-url="{{ route('admin.taxonomies.toggle-status', [$taxonomy, $term]) }}" style="background-color: {{ $term->is_active ? '#28a745' : '#dc3545' }}; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;">
                                            {{ $term->is_active ? '顯示' : '不顯示' }}
                                        </span>
                                    </td>

                                    @if($trash ?? false)
                                        <td>
                                            <form method="post" action="{{ route('admin.taxonomies.restore', [$taxonomy, $term->id]) }}" class="js-taxonomy-restore-form">
                                                @csrf
                                                <button class="btn btn-success" type="submit" title="還原"><i class="fas fa-undo"></i></button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" action="{{ route('admin.taxonomies.force-delete', [$taxonomy, $term->id]) }}" class="js-taxonomy-force-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="永久刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @else
                                        <td><a class="btn btn-info" href="{{ route('admin.taxonomies.edit', [$taxonomy, $term]) }}" title="編輯"><i class="fas fa-edit"></i></a></td>
                                        <td>
                                            <form method="post" action="{{ route('admin.taxonomies.destroy', [$taxonomy, $term]) }}" class="js-taxonomy-delete-form">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger" type="submit" title="刪除"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $useHierarchy ? 8 : 7 }}" class="text-center text-muted cms-table-empty">No data available in table</td>
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
                                顯示第 {{ $terms->firstItem() ?? 0 }} 至 {{ $terms->lastItem() ?? 0 }} 項結果，共 {{ $terms->total() }} 項
                            </div>

                            @if($terms->hasPages())
                                <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                    {{ $terms->links() }}
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

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error('Request failed');
                error.data = data;
                throw error;
            }

            return data;
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

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error('Request failed');
                error.data = data;
                throw error;
            }

            return data;
        }

        async function confirmTaxonomyImpact(data, forceMode = false) {
            const html = String(data.message || '').replace(/\n/g, '<br>');
            const result = await Swal.fire({
                title: data.title || (forceMode ? '分類仍有關聯資料' : '分類內還有資料'),
                html: '<div style="line-height:1.8;text-align:left;">' + html + '</div>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: forceMode ? '解除關聯並永久刪除' : '確定移至垃圾桶',
                cancelButtonText: '取消',
                confirmButtonColor: forceMode ? '#dc3545' : '#3085d6',
                cancelButtonColor: '#6c757d',
                width: '600px',
                reverseButtons: true,
            });

            return result.isConfirmed;
        }

        function redirectAfterTaxonomyAction(data) {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            window.location.reload();
        }

        document.querySelectorAll('.js-taxonomy-sort').forEach(function (select) {
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

        document.querySelectorAll('.js-taxonomy-status').forEach(function (badge) {
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

        document.querySelectorAll('.js-taxonomy-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要移至垃圾桶嗎？')) {
                    return;
                }

                try {
                    redirectAfterTaxonomyAction(await deleteJson(form.action));
                } catch (error) {
                    const data = error.data || {};
                    if (data.needs_confirm && await confirmTaxonomyImpact(data, false)) {
                        redirectAfterTaxonomyAction(await deleteJson(form.action, { confirm: true }));
                    } else {
                        console.error(error);
                    }
                }
            });
        });

        document.querySelectorAll('.js-taxonomy-force-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (!await cmsConfirm('確定要永久刪除嗎？此操作無法復原。', {
                    confirmButtonText: '永久刪除',
                    confirmButtonColor: '#dc3545',
                })) {
                    return;
                }

                try {
                    redirectAfterTaxonomyAction(await deleteJson(form.action));
                } catch (error) {
                    const data = error.data || {};
                    if (data.needs_force && await confirmTaxonomyImpact(data, true)) {
                        redirectAfterTaxonomyAction(await deleteJson(form.action, { force: true }));
                    } else {
                        console.error(error);
                    }
                }
            });
        });

        document.querySelectorAll('.js-taxonomy-restore-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();

                try {
                    redirectAfterTaxonomyAction(await postJson(form.action));
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
                const data = await postJson(@json(route("admin.taxonomies.bulk-action", $taxonomy)), { action, ids });
                if ((data.needs_confirm || data.needs_force) && await confirmTaxonomyImpact(data, data.needs_force)) {
                    redirectAfterTaxonomyAction(await postJson(@json(route("admin.taxonomies.bulk-action", $taxonomy)), {
                        action,
                        ids,
                        confirm: data.needs_confirm,
                        force: data.needs_force,
                    }));
                    return;
                }
                redirectAfterTaxonomyAction(data);
            } catch (error) {
                console.error(error);
            }
        });
    });
</script>
@endpush
