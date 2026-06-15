@extends('admin.partials.shell')

@php
    $folderOptions = function (array $nodes, ?int $selectedId = null, int $depth = 0) use (&$folderOptions): string {
        $html = '';
        foreach ($nodes as $node) {
            $id = (int) $node['id'];
            $label = str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . e($node['name']);
            $selected = $selectedId === $id ? ' selected' : '';
            $html .= '<option value="' . $id . '"' . $selected . '>' . $label . '</option>';
            $html .= $folderOptions($node['children'] ?? [], $selectedId, $depth + 1);
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
<div
    class="media-library-page"
    data-current-folder-id="{{ $folderId ?? '' }}"
>
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
                    <i class="fas fa-trash-alt"></i> 垃圾桶 ({{ $trashCount }})
                </a>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-xl-2 mb-3 mb-lg-0">
            <section class="card card-modern media-folder-card">
                <div class="card-body">
                    <a
                        @class(['media-folder-node', 'media-folder-dropzone', 'active' => !$folderId && !$trash])
                        href="{{ route('admin.media-library.index') }}"
                        data-folder-id=""
                    >
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
                                <option value="date_desc" @selected($sort === 'date_desc')>最新優先</option>
                                <option value="date_asc" @selected($sort === 'date_asc')>最舊優先</option>
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
                                <div class="media-folder-tile media-folder-dropzone" data-folder-id="{{ $childFolder->id }}">
                                    <a href="{{ route('admin.media-library.index', ['folder_id' => $childFolder->id]) }}">
                                        <i class="fas fa-folder"></i>
                                        <strong>{{ $childFolder->name }}</strong>
                                        <span>{{ $childFolder->active_media_count }} 張圖片 / {{ $childFolder->active_children_count }} 個資料夾</span>
                                    </a>
                                    <div class="media-folder-actions">
                                        <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#renameFolder{{ $childFolder->id }}" title="重新命名">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" action="{{ route('admin.media-library.folders.destroy', $childFolder) }}" data-confirm="確定刪除「{{ $childFolder->name }}」？資料夾內的子資料夾與圖片會一起移到垃圾桶。">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit" title="刪除資料夾">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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
                            @php
                                $isImage = str_starts_with((string) $media->mime_type, 'image/');
                                $mediaUrl = $media->getUrl();
                            @endphp
                            <article
                                class="media-card"
                                draggable="{{ !$trash ? 'true' : 'false' }}"
                                data-media-id="{{ $media->id }}"
                                data-current-folder-id="{{ $media->folder_id ?? '' }}"
                                data-move-url="{{ route('admin.media-library.media.move', $media) }}"
                            >
                                <button
                                    class="media-thumb js-open-lightbox"
                                    type="button"
                                    data-url="{{ $mediaUrl }}"
                                    data-title="{{ $media->name }}"
                                    @disabled(!$isImage)
                                >
                                    @if($isImage)
                                        <img src="{{ $mediaUrl }}" alt="{{ $media->alt_text ?? $media->name }}" loading="lazy">
                                    @else
                                        <i class="fas fa-file"></i>
                                    @endif
                                </button>
                                <div class="media-info">
                                    <strong title="{{ $media->name }}">{{ $media->name }}</strong>
                                    <span>{{ $media->file_name }}</span>
                                    <span>{{ $media->human_readable_size }}</span>
                                </div>
                                <div class="media-actions">
                                    @if($trash)
                                        <form method="post" action="{{ route('admin.media-library.media.restore', $media->id) }}">
                                            @csrf
                                            <button class="btn btn-success btn-sm" type="submit" title="還原">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('admin.media-library.media.force-delete', $media->id) }}" data-confirm="確定永久刪除？這個動作無法還原。">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit" title="永久刪除">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-light btn-sm js-copy-url" type="button" data-url="{{ $mediaUrl }}" title="複製 URL">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button class="btn btn-info btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editMedia{{ $media->id }}" title="編輯">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="post" action="{{ route('admin.media-library.media.destroy', $media) }}" data-confirm="確定將圖片移到垃圾桶？">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm" type="submit" title="刪除">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
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
                                                        {!! $folderOptions($folderTree, $media->folder_id ? (int) $media->folder_id : null) !!}
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
                            <div class="text-center text-muted cms-table-empty d-flex align-items-center justify-content-center">
                                No images available
                            </div>
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
                <div class="text-danger text-2 mt-2">支援 jpg、png、gif、webp，單檔上限 10MB</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="submit">上傳</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade media-lightbox-modal" id="mediaLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaLightboxTitle">圖片預覽</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <img id="mediaLightboxImage" src="" alt="">
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .media-folder-card .card-body{padding:14px}
    .media-folder-node{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:4px;color:#333;text-decoration:none;font-size:13px;border:1px solid transparent}
    .media-folder-node:hover,.media-folder-node.active{background:#0088cc;color:#fff;text-decoration:none}
    .media-folder-node.is-drag-over,.media-folder-tile.is-drag-over{border-color:#0088cc;background:#e8f6ff;color:#333}
    .media-folder-children{padding-left:16px}
    .media-folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px}
    .media-folder-tile{display:flex;align-items:center;justify-content:space-between;border:1px solid #e1e1e1;border-radius:4px;padding:14px;background:#fbfbfb}
    .media-folder-tile>a{display:flex;flex-direction:column;gap:4px;color:#333;text-decoration:none;min-width:0}
    .media-folder-tile>a i{color:#f0ad4e;font-size:24px}
    .media-folder-tile>a span{color:#777;font-size:12px}
    .media-folder-actions{display:flex;gap:6px;align-items:center}
    .media-folder-actions form{margin:0}
    .media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px}
    .media-card{border:1px solid #e2e2e2;border-radius:5px;background:#fff;overflow:hidden;transition:opacity .15s ease, transform .15s ease}
    .media-card.is-dragging{opacity:.45;transform:scale(.98)}
    .media-thumb{display:flex;align-items:center;justify-content:center;width:100%;height:145px;background:#f4f4f4;color:#999;text-decoration:none;border:0;padding:0;cursor:zoom-in}
    .media-thumb:disabled{cursor:default}
    .media-thumb img{width:100%;height:100%;object-fit:cover}
    .media-thumb i{font-size:42px}
    .media-info{display:flex;flex-direction:column;gap:3px;padding:10px 12px;min-height:76px}
    .media-info strong{font-size:13px;color:#222;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-info span{font-size:12px;color:#777;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-actions{display:flex;gap:6px;align-items:center;padding:10px 12px;border-top:1px solid #eee;background:#fafafa}
    .media-actions form{margin:0}
    .media-lightbox-modal .modal-body{display:flex;align-items:center;justify-content:center;background:#111;min-height:60vh}
    .media-lightbox-modal img{display:block;max-width:100%;max-height:75vh;object-fit:contain}
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const page = document.querySelector('.media-library-page');
        const currentFolderId = page?.dataset.currentFolderId || '';
        let draggedCard = null;

        document.querySelectorAll('.js-copy-url').forEach(function (button) {
            button.addEventListener('click', async function () {
                await navigator.clipboard.writeText(button.dataset.url);
                window.cmsAlert('圖片 URL 已複製', 'success');
            });
        });

        const lightboxEl = document.getElementById('mediaLightboxModal');
        const lightboxImage = document.getElementById('mediaLightboxImage');
        const lightboxTitle = document.getElementById('mediaLightboxTitle');
        const lightbox = lightboxEl && window.bootstrap ? new bootstrap.Modal(lightboxEl) : null;

        document.querySelectorAll('.js-open-lightbox').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!lightbox || button.disabled) return;
                lightboxImage.src = button.dataset.url;
                lightboxImage.alt = button.dataset.title || '';
                lightboxTitle.textContent = button.dataset.title || '圖片預覽';
                lightbox.show();
            });
        });

        lightboxEl?.addEventListener('hidden.bs.modal', function () {
            lightboxImage.src = '';
        });

        document.querySelectorAll('.media-card[draggable="true"]').forEach(function (card) {
            card.addEventListener('dragstart', function (event) {
                draggedCard = card;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.dataset.mediaId);
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('is-dragging');
                draggedCard = null;
                document.querySelectorAll('.is-drag-over').forEach(function (node) {
                    node.classList.remove('is-drag-over');
                });
            });
        });

        document.querySelectorAll('.media-folder-dropzone').forEach(function (dropzone) {
            dropzone.addEventListener('dragover', function (event) {
                if (!draggedCard) return;
                event.preventDefault();
                dropzone.classList.add('is-drag-over');
                event.dataTransfer.dropEffect = 'move';
            });

            dropzone.addEventListener('dragleave', function () {
                dropzone.classList.remove('is-drag-over');
            });

            dropzone.addEventListener('drop', async function (event) {
                if (!draggedCard) return;
                event.preventDefault();
                dropzone.classList.remove('is-drag-over');

                const targetFolderId = dropzone.dataset.folderId || '';
                if ((draggedCard.dataset.currentFolderId || '') === targetFolderId) {
                    window.cmsAlert('圖片已在這個資料夾中', 'info');
                    return;
                }

                try {
                    const response = await fetch(draggedCard.dataset.moveUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ folder_id: targetFolderId || null }),
                    });

                    const payload = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        throw new Error(payload.message || '圖片移動失敗');
                    }

                    draggedCard.dataset.currentFolderId = targetFolderId;
                    if (currentFolderId !== targetFolderId) {
                        draggedCard.remove();
                    }
                    window.cmsAlert(payload.message || '圖片已移動', 'success');
                } catch (error) {
                    window.cmsAlert(error.message || '圖片移動失敗', 'error');
                }
            });
        });
    });
</script>
@endpush
