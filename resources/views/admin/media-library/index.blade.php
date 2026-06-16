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
<div class="media-library-page" data-current-folder-id="{{ $folderId ?? '' }}">
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
                                @unless($trash)
                                    <button class="media-drag-handle" type="button" draggable="true" title="拖曳移動圖片">
                                        <i class="fas fa-grip-vertical"></i>
                                    </button>
                                @endunless

                                <button class="media-thumb js-open-lightbox" type="button" data-url="{{ $mediaUrl }}" data-title="{{ $media->name }}" @disabled(!$isImage)>
                                    @if($isImage)
                                        <img src="{{ $mediaUrl }}" alt="{{ $media->alt_text ?? $media->name }}" loading="lazy" draggable="false">
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
                            <div class="media-empty-state">
                                <i class="fas fa-images"></i>
                                <strong>{{ $trash ? '垃圾桶目前是空的' : '這個資料夾還沒有圖片' }}</strong>
                                @unless($trash)
                                    <span>可以點上方「上傳圖片」新增圖片。</span>
                                @endunless
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
    <div class="modal-dialog modal-lg">
        <form class="modal-content js-gallery-upload-form" method="post" enctype="multipart/form-data" action="{{ route('admin.media-library.media.store') }}">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">
            <div class="modal-header">
                <h5 class="modal-title">上傳圖片</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="gallery-upload-drop" id="galleryUploadDrop">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <strong>拖曳圖片到這裡，或點擊選擇檔案</strong>
                    <span>支援 jpg、png、gif、webp，單檔上限 10MB</span>
                </div>
                <input class="d-none" id="galleryUploadInput" type="file" accept="image/*" multiple>
                <div class="gallery-upload-list" id="galleryUploadList"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" id="galleryUploadClear" type="button" disabled>
                    <i class="fas fa-times-circle"></i> 清空
                </button>
                <button class="btn btn-primary" id="galleryUploadStart" type="button" disabled>
                    <i class="fas fa-upload"></i> 開始上傳
                </button>
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
    .media-card{position:relative;border:1px solid #e2e2e2;border-radius:5px;background:#fff;overflow:hidden;transition:opacity .15s ease, transform .15s ease;cursor:grab}
    .media-card:active{cursor:grabbing}
    .media-card.is-dragging{opacity:.45;transform:scale(.98)}
    .media-drag-handle{position:absolute;top:8px;left:8px;z-index:2;width:28px;height:28px;border:0;border-radius:4px;background:rgba(0,0,0,.62);color:#fff;cursor:grab}
    .media-drag-handle:active{cursor:grabbing}
    .media-thumb{display:flex;align-items:center;justify-content:center;width:100%;height:145px;background:#f4f4f4;color:#999;text-decoration:none;border:0;padding:0;cursor:zoom-in}
    .media-thumb:disabled{cursor:default}
    .media-thumb img{width:100%;height:100%;object-fit:cover;pointer-events:none}
    .media-thumb i{font-size:42px}
    .media-info{display:flex;flex-direction:column;gap:3px;padding:10px 12px;min-height:76px}
    .media-info strong{font-size:13px;color:#222;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-info span{font-size:12px;color:#777;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .media-actions{display:flex;gap:6px;align-items:center;padding:10px 12px;border-top:1px solid #eee;background:#fafafa}
    .media-actions form{margin:0}
    .media-empty-state{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:180px;border:1px dashed #d8dde6;border-radius:6px;background:#fbfcfe;color:#777;text-align:center}
    .media-empty-state i{font-size:32px;color:#b8c1cc}
    .media-empty-state strong{font-size:15px;color:#555}
    .media-empty-state span{font-size:13px;color:#888}
    .media-lightbox-modal .modal-body{display:flex;align-items:center;justify-content:center;background:#111;min-height:60vh}
    .media-lightbox-modal img{display:block;max-width:100%;max-height:75vh;object-fit:contain}
    .gallery-upload-drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:190px;padding:32px;border:2px dashed #4a8ef0;border-radius:10px;background:#f9fbff;color:#337ab7;text-align:center;cursor:pointer;transition:background .2s ease,border-color .2s ease,color .2s ease}
    .gallery-upload-drop i{font-size:42px}
    .gallery-upload-drop strong{font-size:16px;color:#1f5f9d}
    .gallery-upload-drop span{font-size:13px;color:#777}
    .gallery-upload-drop.is-drag-over{background:#d9e6ff;border-color:#105ac4;color:#105ac4}
    .gallery-upload-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:15px}
    .gallery-upload-item{display:grid;grid-template-columns:64px 1fr auto;gap:10px;align-items:center;border:1px solid #e0e3ea;border-radius:8px;background:#fff;padding:8px}
    .gallery-upload-preview{width:64px;height:52px;border-radius:6px;background:#f1f1f1;overflow:hidden}
    .gallery-upload-preview img{width:100%;height:100%;object-fit:cover}
    .gallery-upload-meta{min-width:0}
    .gallery-upload-name{font-size:13px;font-weight:600;color:#222;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .gallery-upload-size{font-size:12px;color:#777}
    .gallery-upload-status{font-size:12px;color:#777;margin-top:3px}
    .gallery-upload-status.success{color:#1f9d45}
    .gallery-upload-status.fail{color:#d2322d}
    .gallery-upload-progress{height:6px;border-radius:999px;background:#edf0f5;overflow:hidden;margin-top:6px}
    .gallery-upload-progress span{display:block;width:0%;height:100%;background:#4a8ef0;transition:width .15s ease}
    .gallery-upload-remove{width:28px;height:28px;border:0;border-radius:4px;background:#d9534f;color:#fff;line-height:1}
    @media (max-width: 767px){.gallery-upload-list{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const page = document.querySelector('.media-library-page');
        const currentFolderId = page?.dataset.currentFolderId || '';
        let draggedCard = null;
        let dragClearTimer = null;
        let uploadQueue = [];
        const uploadMaxBytes = 10 * 1024 * 1024;

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
                if (event.target.closest('.media-actions')) {
                    event.preventDefault();
                    return;
                }
                clearTimeout(dragClearTimer);
                draggedCard = card;
                card.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('application/x-media-id', card.dataset.mediaId);
                event.dataTransfer.setData('text/plain', card.dataset.mediaId);
            });

            card.addEventListener('dragend', function () {
                card.classList.remove('is-dragging');
                dragClearTimer = setTimeout(function () {
                    draggedCard = null;
                }, 120);
                document.querySelectorAll('.is-drag-over').forEach(function (node) {
                    node.classList.remove('is-drag-over');
                });
            });

            card.querySelector('.media-drag-handle')?.addEventListener('click', function (event) {
                event.preventDefault();
            });
        });

        document.querySelectorAll('.media-folder-dropzone').forEach(function (dropzone) {
            dropzone.addEventListener('dragover', function (event) {
                if (!draggedCard && !Array.from(event.dataTransfer.types || []).includes('text/plain')) return;
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('is-drag-over');
                event.dataTransfer.dropEffect = 'move';
            }, true);

            dropzone.addEventListener('dragleave', function (event) {
                if (dropzone.contains(event.relatedTarget)) return;
                dropzone.classList.remove('is-drag-over');
            }, true);

            dropzone.addEventListener('drop', async function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('is-drag-over');
                clearTimeout(dragClearTimer);

                const mediaId = event.dataTransfer.getData('application/x-media-id') || event.dataTransfer.getData('text/plain');
                const card = draggedCard || document.querySelector(`.media-card[data-media-id="${mediaId}"]`);
                if (!card) return;

                const targetFolderId = dropzone.dataset.folderId || '';
                if ((card.dataset.currentFolderId || '') === targetFolderId) {
                    window.cmsAlert('圖片已在這個資料夾中', 'info');
                    return;
                }

                try {
                    const response = await fetch(card.dataset.moveUrl, {
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
                    card.dataset.currentFolderId = targetFolderId;
                    if (currentFolderId !== targetFolderId) {
                        card.remove();
                    }
                    window.cmsAlert(payload.message || '圖片已移動', 'success');
                } catch (error) {
                    window.cmsAlert(error.message || '圖片移動失敗', 'error');
                } finally {
                    draggedCard = null;
                }
            }, true);
        });

        const uploadForm = document.querySelector('.js-gallery-upload-form');
        const uploadDrop = document.getElementById('galleryUploadDrop');
        const uploadInput = document.getElementById('galleryUploadInput');
        const uploadList = document.getElementById('galleryUploadList');
        const uploadStart = document.getElementById('galleryUploadStart');
        const uploadClear = document.getElementById('galleryUploadClear');

        function formatSize(bytes) {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
            return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
        }

        function syncUploadButtons() {
            const hasPending = uploadQueue.some(item => !item.uploaded && !item.failed && !item.uploading);
            uploadStart.disabled = !hasPending;
            uploadClear.disabled = uploadQueue.length === 0 || uploadQueue.some(item => item.uploading);
        }

        function renderUploadItem(item) {
            const row = document.createElement('div');
            row.className = 'gallery-upload-item';
            row.dataset.uploadId = item.id;
            row.innerHTML = `
                <div class="gallery-upload-preview"><img alt=""></div>
                <div class="gallery-upload-meta">
                    <div class="gallery-upload-name"></div>
                    <div class="gallery-upload-size"></div>
                    <div class="gallery-upload-progress"><span></span></div>
                    <div class="gallery-upload-status">準備上傳...</div>
                </div>
                <button class="gallery-upload-remove" type="button" title="移除">×</button>
            `;

            row.querySelector('.gallery-upload-name').textContent = item.file.name;
            row.querySelector('.gallery-upload-size').textContent = formatSize(item.file.size);
            item.progress = row.querySelector('.gallery-upload-progress span');
            item.status = row.querySelector('.gallery-upload-status');
            item.removeButton = row.querySelector('.gallery-upload-remove');
            item.element = row;

            const reader = new FileReader();
            reader.onload = function (event) {
                row.querySelector('img').src = event.target.result;
            };
            reader.readAsDataURL(item.file);

            item.removeButton.addEventListener('click', function () {
                if (item.xhr) {
                    item.canceled = true;
                    item.xhr.abort();
                }
                uploadQueue = uploadQueue.filter(queueItem => queueItem.id !== item.id);
                row.remove();
                syncUploadButtons();
            });

            uploadList.appendChild(row);
        }

        function addUploadFiles(files) {
            Array.from(files).forEach(function (file) {
                if (!file.type.startsWith('image/')) {
                    window.cmsAlert(`${file.name} 不是圖片檔`, 'warning');
                    return;
                }

                const item = {
                    id: `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                    file,
                    uploaded: false,
                    failed: false,
                    uploading: false,
                    canceled: false,
                    xhr: null,
                };

                renderUploadItem(item);
                uploadQueue.push(item);

                if (file.size > uploadMaxBytes) {
                    item.failed = true;
                    item.status.textContent = '檔案超過 10MB';
                    item.status.classList.add('fail');
                    item.progress.style.width = '100%';
                }
            });
            syncUploadButtons();
        }

        function uploadItem(item, callback) {
            if (item.uploaded || item.failed || item.uploading) {
                callback(false);
                return;
            }

            const xhr = new XMLHttpRequest();
            const formData = new FormData();
            item.xhr = xhr;
            item.uploading = true;
            item.status.textContent = '上傳中... 0%';
            syncUploadButtons();

            formData.append('_token', csrfToken);
            formData.append('folder_id', uploadForm.querySelector('[name="folder_id"]').value || '');
            formData.append('images[]', item.file);

            xhr.upload.onprogress = function (event) {
                if (!event.lengthComputable) return;
                const percent = Math.round((event.loaded / event.total) * 100);
                item.progress.style.width = `${percent}%`;
                item.status.textContent = `上傳中... ${percent}%`;
            };

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                item.uploading = false;
                item.xhr = null;

                if (xhr.status >= 200 && xhr.status < 300) {
                    item.uploaded = true;
                    item.progress.style.width = '100%';
                    item.status.textContent = '上傳完成';
                    item.status.classList.remove('fail');
                    item.status.classList.add('success');
                    item.removeButton.remove();
                    callback(true);
                    return;
                }

                if (!item.canceled) {
                    let message = '上傳失敗';
                    try {
                        const payload = JSON.parse(xhr.responseText);
                        message = payload.message || message;
                    } catch (error) {
                        message = xhr.status ? `上傳失敗 (${xhr.status})` : '上傳已取消';
                    }
                    item.failed = true;
                    item.status.textContent = message;
                    item.status.classList.add('fail');
                }
                callback(false);
            };

            xhr.open('POST', uploadForm.action, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            xhr.send(formData);
        }

        uploadDrop?.addEventListener('click', function () {
            uploadInput.click();
        });

        uploadInput?.addEventListener('change', function () {
            addUploadFiles(uploadInput.files);
            uploadInput.value = '';
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            uploadDrop?.addEventListener(eventName, function (event) {
                event.preventDefault();
                uploadDrop.classList.add('is-drag-over');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            uploadDrop?.addEventListener(eventName, function () {
                uploadDrop.classList.remove('is-drag-over');
            });
        });

        uploadDrop?.addEventListener('drop', function (event) {
            event.preventDefault();
            addUploadFiles(event.dataTransfer.files);
        });

        uploadClear?.addEventListener('click', function () {
            uploadQueue.forEach(function (item) {
                if (item.xhr) {
                    item.canceled = true;
                    item.xhr.abort();
                }
            });
            uploadQueue = [];
            uploadList.innerHTML = '';
            syncUploadButtons();
        });

        uploadStart?.addEventListener('click', function () {
            const items = uploadQueue.filter(item => !item.uploaded && !item.failed);
            if (items.length === 0) return;

            let remaining = items.length;
            let successCount = 0;
            uploadStart.disabled = true;

            items.forEach(function (item) {
                uploadItem(item, function (success) {
                    if (success) successCount++;
                    remaining--;
                    if (remaining === 0) {
                        syncUploadButtons();
                        if (successCount > 0) {
                            window.cmsAlert(`已上傳 ${successCount} 張圖片`, 'success').then(function () {
                                window.location.reload();
                            });
                        }
                    }
                });
            });
        });
    });
</script>
@endpush
