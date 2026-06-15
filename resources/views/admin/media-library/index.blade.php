@extends('admin.partials.shell')

@php
    $folderOptions = function (array $nodes, int $depth = 0) use (&$folderOptions): string {
        $html = '';
        foreach ($nodes as $node) {
            $label = str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . e($node['name']);
            $html .= '<option value="' . (int) $node['id'] . '">' . $label . '</option>';
            $html .= $folderOptions($node['children'] ?? [], $depth + 1);
        }
        return $html;
    };
@endphp

@section('title', '圖片庫')
@section('page_title', '圖片庫')

@section('breadcrumb')
    <li><span>圖片庫</span></li>
    <li><span>{{ $trash ? '垃圾桶' : '圖片列表' }}</span></li>
@endsection

@section('content')
<div class="media-library-page">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row align-items-center mb-3">
        <div class="col-12 col-lg-auto mb-3 mb-lg-0">
            @if($folder)
                <span class="me-3">目前位置：{{ $folder->pathLabel() }}</span>
                <a class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.media-library.index', array_filter(['folder_id' => $folder->parent_id])) }}">
                    <i class="fas fa-arrow-left"></i> 返回上一層
                </a>
            @else
                <span class="me-3">目前位置：根目錄</span>
            @endif
            @if($trash)
                <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.media-library.index', array_filter(['folder_id' => $folderId])) }}">
                    <i class="fas fa-images"></i> 回圖片庫
                </a>
            @else
                <button class="btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" type="button" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-cloud-upload-alt"></i> 上傳圖片
                </button>
                <button class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" type="button" data-bs-toggle="modal" data-bs-target="#folderModal">
                    <i class="fas fa-folder-plus"></i> 新增資料夾
                </button>
                <a class="btn btn-light btn-md font-weight-semibold btn-py-2 px-4" href="{{ route('admin.media-library.index', array_filter(['folder_id' => $folderId, 'trash' => 1])) }}">
                    <i class="fas fa-trash-alt"></i> 垃圾桶({{ $trashCount }})
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-xl-2 mb-3 mb-lg-0">
            <section class="card card-modern media-folder-card">
                <div class="card-body">
                    <a @class(['media-folder-node', 'active' => !$folderId && !$trash]) href="{{ route('admin.media-library.index') }}">
                        <i class="fas fa-home"></i>
                        <span>根目錄</span>
                    </a>
                    @include('admin.media-library.partials.folder-tree', ['nodes' => $folderTree, 'folderId' => $folderId])
                </div>
            </section>
        </div>

        <div class="col-lg-9 col-xl-10">
            <section class="card card-modern">
                <div class="card-body">
                    <form method="get" class="row align-items-center mb-4">
                        @if($folderId)
                            <input type="hidden" name="folder_id" value="{{ $folderId }}">
                        @endif
                        @if($trash)
                            <input type="hidden" name="trash" value="1">
                        @endif
                        <div class="col-6 col-lg-auto ms-auto mb-2 mb-lg-0">
                            <select class="form-control select-style-1" name="sort" onchange="this.form.submit()">
                                <option value="date_desc" @selected($sort === 'date_desc')>最新上傳</option>
                                <option value="date_asc" @selected($sort === 'date_asc')>最舊上傳</option>
                                <option value="name_asc" @selected($sort === 'name_asc')>名稱 A-Z</option>
                                <option value="name_desc" @selected($sort === 'name_desc')>名稱 Z-A</option>
                            </select>
                        </div>
                        <div class="col-6 col-lg-auto mb-2 mb-lg-0">
                            <select class="form-control select-style-1" name="per_page" onchange="this.form.submit()">
                                @foreach([24, 48, 96] as $size)
                                    <option value="{{ $size }}" @selected((int) request('per_page', 24) === $size)>{{ $size }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-lg-3">
                            <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                <div class="input-group">
                                    <input type="text" class="search-term form-control" name="keyword" value="{{ $keyword }}" placeholder="Search Image">
                                    <button class="btn btn-default search-button" type="submit"><i class="bx bx-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if(!$trash && $folders->isNotEmpty())
                        <div class="media-folder-grid mb-4">
                            @foreach($folders as $childFolder)
                                <div class="media-folder-tile">
                                    <a href="{{ route('admin.media-library.index', ['folder_id' => $childFolder->id]) }}">
                                        <i class="fas fa-folder"></i>
                                        <strong>{{ $childFolder->name }}</strong>
                                        <span>{{ $childFolder->active_media_count }} 張圖片 / {{ $childFolder->active_children_count }} 資料夾</span>
                                    </a>
                                    <div class="media-folder-actions">
                                        <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#renameFolder{{ $childFolder->id }}"><i class="fas fa-edit"></i></button>
                                        <form method="post" action="{{ route('admin.media-library.folders.destroy', $childFolder) }}" data-confirm="確定刪除這個資料夾？空資料夾才可刪除。">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                </div>

                                <div class="modal fade" id="renameFolder{{ $childFolder->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form class="modal-content" method="post" action="{{ route('admin.media-library.folders.update', $childFolder) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">重新命名資料夾</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input class="form-control" name="name" value="{{ $childFolder->name }}" required>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">儲存</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="media-grid">
                        @forelse($mediaItems as $media)
                            <article class="media-card">
                                <a class="media-thumb" href="{{ $media->getUrl() }}" target="_blank" rel="noopener">
                                    @if(str_starts_with((string) $media->mime_type, 'image/'))
                                        <img src="{{ $media->getUrl() }}" alt="{{ $media->alt_text ?? $media->name }}">
                                    @else
                                        <i class="fas fa-file"></i>
                                    @endif
                                </a>
                                <div class="media-info">
                                    <strong title="{{ $media->name }}">{{ $media->name }}</strong>
                                    <span>{{ $media->file_name }}</span>
                                    <span>{{ $media->human_readable_size }}</span>
                                </div>
                                <div class="media-actions">
                                    @if($trash)
                                        <form method="post" action="{{ route('admin.media-library.media.restore', $media->id) }}">
                                            @csrf
                                            <button class="btn btn-success btn-sm" type="submit" title="還原"><i class="fas fa-undo"></i></button>
                                        </form>
                                        <form method="post" action="{{ route('admin.media-library.media.force-delete', $media->id) }}" data-confirm="確定永久刪除？這個動作無法還原。">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit" title="永久刪除"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    @else
                                        <button class="btn btn-light btn-sm js-copy-url" type="button" data-url="{{ $media->getUrl() }}" title="複製 URL"><i class="fas fa-link"></i></button>
                                        <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editMedia{{ $media->id }}" title="編輯"><i class="fas fa-edit"></i></button>
                                        <form method="post" action="{{ route('admin.media-library.media.destroy', $media) }}" data-confirm="確定將圖片移到垃圾桶？">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit" title="刪除"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </article>

                            @unless($trash)
                                <div class="modal fade" id="editMedia{{ $media->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <form class="modal-content" method="post" action="{{ route('admin.media-library.media.update', $media) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title">編輯圖片</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">圖片名稱</label>
                                                    <input class="form-control" name="name" value="{{ $media->name }}" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">ALT / 圖片說明</label>
                                                    <input class="form-control" name="alt_text" value="{{ $media->alt_text }}">
                                                </div>
                                                <div>
                                                    <label class="form-label">資料夾</label>
                                                    <select class="form-control" name="folder_id">
                                                        <option value="">根目錄</option>
                                                        {!! str_replace('value="' . $media->folder_id . '"', 'value="' . $media->folder_id . '" selected', $folderOptions($folderTree)) !!}
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary" type="submit">儲存</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endunless
                        @empty
                            <div class="text-center text-muted cms-table-empty d-flex align-items-center justify-content-center">No images available</div>
                        @endforelse
                    </div>

                    @if($mediaItems->hasPages())
                        <div class="mt-4">
                            {{ $mediaItems->links() }}
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="folderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" action="{{ route('admin.media-library.folders.store') }}">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $folderId }}">
            <div class="modal-header">
                <h5 class="modal-title">新增資料夾</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input class="form-control" name="name" placeholder="資料夾名稱" required>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="submit">建立</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" enctype="multipart/form-data" action="{{ route('admin.media-library.media.store') }}">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">
            <div class="modal-header">
                <h5 class="modal-title">上傳圖片</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input class="form-control" type="file" name="images[]" accept="image/*" multiple required>
                <div class="text-danger text-2 mt-2">支援 jpg, png, gif, webp，單檔最大 10MB</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="submit">上傳</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .media-folder-card .card-body{padding:14px}
    .media-folder-node{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:4px;color:#333;text-decoration:none;font-size:13px}
    .media-folder-node:hover,.media-folder-node.active{background:#0088cc;color:#fff;text-decoration:none}
    .media-folder-children{padding-left:16px}
    .media-folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
    .media-folder-tile{display:flex;align-items:center;justify-content:space-between;border:1px solid #e1e1e1;border-radius:4px;padding:14px;background:#fbfbfb}
    .media-folder-tile>a{display:flex;flex-direction:column;gap:4px;color:#333;text-decoration:none;min-width:0}
    .media-folder-tile>a i{color:#f0ad4e;font-size:24px}
    .media-folder-tile>a span{color:#777;font-size:12px}
    .media-folder-actions{display:flex;gap:6px;align-items:center}
    .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}
    .media-card{border:1px solid #e2e2e2;border-radius:5px;background:#fff;overflow:hidden}
    .media-thumb{display:flex;align-items:center;justify-content:center;height:145px;background:#f4f4f4;color:#999;text-decoration:none}
    .media-thumb img{width:100%;height:100%;object-fit:cover}
    .media-thumb i{font-size:42px}
    .media-info{display:flex;flex-direction:column;gap:3px;padding:10px 12px;min-height:76px}
    .media-info strong{font-size:13px;color:#222;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-info span{font-size:12px;color:#777;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-actions{display:flex;gap:6px;align-items:center;padding:10px 12px;border-top:1px solid #eee;background:#fafafa}
    .media-actions form{margin:0}
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-copy-url').forEach(function (button) {
            button.addEventListener('click', async function () {
                await navigator.clipboard.writeText(button.dataset.url);
                window.cmsAlert('圖片 URL 已複製', 'success');
            });
        });
    });
</script>
@endpush
