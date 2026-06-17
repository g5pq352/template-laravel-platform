@php
    $containsActiveFolder = function (array $children) use (&$containsActiveFolder, $folderId): bool {
        foreach ($children as $child) {
            if ((int) ($folderId ?? 0) === (int) $child['id']) {
                return true;
            }

            if (!empty($child['children']) && $containsActiveFolder($child['children'])) {
                return true;
            }
        }

        return false;
    };
@endphp

@foreach($nodes as $node)
    @php
        $isActive = (int) ($folderId ?? 0) === (int) $node['id'];
        $hasChildren = !empty($node['children']);
        $shouldExpand = $isActive || ($hasChildren && $containsActiveFolder($node['children']));
    @endphp
    <div @class(['media-folder-node-wrap', 'is-collapsed' => $hasChildren && !$shouldExpand])>
        <div class="media-folder-node-row">
            <a
                @class(['media-folder-node', 'media-folder-dropzone', 'active' => $isActive])
                href="{{ route('admin.media-library.index', ['folder_id' => $node['id']]) }}"
                data-folder-id="{{ $node['id'] }}"
            >
                <span @class(['media-folder-toggle', 'has-children' => $hasChildren]) aria-hidden="true">
                    @if($hasChildren)
                        <i class="bx bx-chevron-right"></i>
                    @endif
                </span>
                <i class="bx bx-folder media-folder-icon"></i>
                <span>{{ $node['name'] }}</span>
            </a>
            <div class="media-folder-node-tools">
                <button class="media-folder-node-tool" type="button" data-bs-toggle="modal" data-bs-target="#renameTreeFolder{{ $node['id'] }}" title="重新命名">
                    <i class="fas fa-pen"></i>
                </button>
                <form method="post" action="{{ route('admin.media-library.folders.destroy', $node['id']) }}" data-confirm="確定刪除「{{ $node['name'] }}」？資料夾內的子資料夾與圖片會一起移到垃圾桶。">
                    @csrf
                    @method('DELETE')
                    <button class="media-folder-node-tool is-danger" type="submit" title="刪除">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="modal fade" id="renameTreeFolder{{ $node['id'] }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="{{ route('admin.media-library.folders.update', $node['id']) }}">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">重新命名資料夾</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input class="form-control" name="name" value="{{ $node['name'] }}" required>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" type="submit">儲存</button>
                    </div>
                </form>
            </div>
        </div>

        @if($hasChildren)
            <div class="media-folder-children">
                @include('admin.media-library.partials.folder-tree', ['nodes' => $node['children'], 'folderId' => $folderId])
            </div>
        @endif
    </div>
@endforeach
