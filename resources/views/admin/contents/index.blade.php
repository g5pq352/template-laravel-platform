@extends('admin.partials.shell')

@section('title', '內容管理')
@section('page_title', '內容管理')

@section('breadcrumb')
    <li><span>內容管理</span></li>
@endsection

@section('content')
<div class="row">
    <div class="col">
        <section class="card card-modern">
            <header class="card-header">
                <div class="card-actions">
                    <a href="#" class="card-action card-action-toggle" data-card-toggle></a>
                </div>
                <h2 class="card-title">內容列表</h2>
            </header>

            <div class="card-body">
                <div class="datatables-header-footer-wrapper mt-2">
                    <div class="datatable-header">
                        <form method="get" action="{{ route('admin.contents.index') }}" class="row align-items-center g-2">
                            <div class="col-md-4 col-lg-3">
                                <select class="form-control" name="content_type_id">
                                    <option value="">全部類型</option>
                                    @foreach($contentTypes as $type)
                                        <option value="{{ $type->id }}" @selected((int) $typeId === (int) $type->id)>{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 col-lg-4">
                                <input class="form-control" type="search" name="search" value="{{ $keyword }}" placeholder="搜尋標題、摘要">
                            </div>
                            <div class="col-md-3 col-lg-2">
                                <button class="btn btn-default w-100" type="submit">
                                    <i class="bx bx-search"></i> 搜尋
                                </button>
                            </div>
                            <div class="col-md-12 col-lg-3 text-lg-end">
                                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.contents.create', array_filter(['content_type_id' => $typeId])) }}">
                                    <i class="bx bx-plus"></i> 新增內容
                                </a>
                            </div>
                        </form>
                    </div>

                    <table class="table table-ecommerce-simple table-striped mb-0" id="datatable-ecommerce-list">
                        <thead>
                            <tr>
                                <th width="34%">標題</th>
                                <th>類型</th>
                                <th>狀態</th>
                                <th>Slug</th>
                                <th>更新時間</th>
                                <th class="text-end" width="150">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contents as $content)
                                @php($translation = $content->translation($site->default_locale))
                                <tr>
                                    <td>
                                        <strong>{{ $translation?->title ?? '未填寫標題' }}</strong>
                                        @if($translation?->summary)
                                            <div class="text-muted text-2 mt-1">{{ $translation->summary }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $content->contentType?->name }}</td>
                                    <td>
                                        <span class="badge badge-status bg-{{ $content->status === 'published' ? 'success' : 'secondary' }}">
                                            {{ match ($content->status) {
                                                'published' => '顯示',
                                                'draft' => '草稿',
                                                'scheduled' => '排程',
                                                'hidden' => '不顯示',
                                                'archived' => '封存',
                                                default => $content->status,
                                            } }}
                                        </span>
                                    </td>
                                    <td>{{ $translation?->slug ?? '-' }}</td>
                                    <td>{{ $content->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-end">
                                        <div class="actions">
                                            <a class="btn btn-default btn-sm" href="{{ route('admin.contents.edit', $content) }}">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <form method="post" action="{{ route('admin.contents.destroy', $content) }}" data-confirm="確定要刪除這筆內容？">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm" type="submit">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">目前沒有內容資料</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="datatable-footer mt-3">
                        {{ $contents->links() }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
