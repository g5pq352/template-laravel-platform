@extends('admin.partials.shell')

@section('title', '多站管理')
@section('page_title', '多站管理')

@section('breadcrumb')
    <li><span>系統管理</span></li>
    <li><span>多站管理</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        <div class="row align-items-center mb-3">
            <div class="col-12 col-lg-auto mb-3 mb-lg-0">
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.sites.create') }}">
                    <i class="fas fa-plus-circle"></i> 新增
                </a>
            </div>
        </div>

        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper dataTables_wrapper mt-2">
                    <div class="datatable-header">
                        <form method="get" class="row align-items-center mb-3">
                            <div class="col-4 col-lg-auto ms-auto ps-lg-1 mb-3 mb-lg-0">
                                <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                    <label class="ws-nowrap me-3 mb-0">狀態:</label>
                                    <select class="form-control select-style-1" name="status" onchange="this.form.submit()">
                                        <option value="">全部</option>
                                        <option value="active" @selected($status === 'active')>啟用</option>
                                        <option value="inactive" @selected($status === 'inactive')>停用</option>
                                    </select>
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
                                        <input type="text" class="search-term form-control" name="keyword" value="{{ $keyword }}" placeholder="Search Site">
                                        <button class="btn btn-default search-button" type="submit"><i class="bx bx-search"></i></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <table class="table table-ecommerce-simple table-striped mb-0 dataTable no-footer" style="min-width: 960px;">
                        <thead>
                            <tr>
                                <th width="220">站台名稱</th>
                                <th width="160">站台代號</th>
                                <th>網域</th>
                                <th width="130">預設語系</th>
                                <th width="90">狀態</th>
                                <th width="30">編輯</th>
                                <th width="30">刪除</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($managedSites as $managedSite)
                                <tr>
                                    <td>
                                        <a class="font-weight-semibold" href="{{ route('admin.sites.edit', $managedSite) }}">{{ $managedSite->name }}</a>
                                        <div class="text-muted text-2">{{ $managedSite->tenant?->name ?? '-' }}</div>
                                    </td>
                                    <td>{{ $managedSite->slug }}</td>
                                    <td>
                                        @forelse($managedSite->domains as $domain)
                                            <span class="d-inline-block me-3">
                                                {{ $domain->domain }}
                                                @if($domain->is_primary)
                                                    <span class="badge badge-info ms-1">主網域</span>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-muted">尚未設定</span>
                                        @endforelse
                                    </td>
                                    <td>{{ $managedSite->default_locale }}</td>
                                    <td>
                                        <span class="btn" style="background-color: {{ $managedSite->status === 'active' ? '#28a745' : '#dc3545' }}; padding: 5px 10px; border-radius: 4px; color: white;">
                                            {{ $managedSite->status === 'active' ? '啟用' : '停用' }}
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-info" href="{{ route('admin.sites.edit', $managedSite) }}" title="編輯"><i class="fas fa-edit"></i></a>
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('admin.sites.destroy', $managedSite) }}" class="js-site-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger" type="submit" title="刪除"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted cms-table-empty">No data available in table</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <hr class="solid mt-5 opacity-4">
                    <div class="datatable-footer">
                        <div class="row align-items-center justify-content-between mt-3">
                            <div class="col-lg-auto text-center order-2 cms-count">
                                顯示第 {{ $managedSites->firstItem() ?? 0 }} 至 {{ $managedSites->lastItem() ?? 0 }} 項結果，共 {{ $managedSites->total() }} 項
                            </div>

                            @if($managedSites->hasPages())
                                <div class="col-lg-auto order-3 mb-3 mb-lg-0">
                                    {{ $managedSites->links() }}
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
        document.querySelectorAll('.js-site-delete-form').forEach(function (form) {
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (!await cmsConfirm('確定刪除這個站台？此動作會移除站台網域設定。')) return;
                form.submit();
            });
        });
    });
</script>
@endpush
