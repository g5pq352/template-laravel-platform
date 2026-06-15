@foreach($nodes as $node)
    @php
        $isActive = (int) ($folderId ?? 0) === (int) $node['id'];
        $hasChildren = !empty($node['children']);
    @endphp
    <div class="media-folder-node-wrap">
        <a
            @class(['media-folder-node', 'media-folder-dropzone', 'active' => $isActive])
            href="{{ route('admin.media-library.index', ['folder_id' => $node['id']]) }}"
            data-folder-id="{{ $node['id'] }}"
        >
            <i class="fas fa-folder"></i>
            <span>{{ $node['name'] }}</span>
        </a>
        @if($hasChildren)
            <div class="media-folder-children">
                @include('admin.media-library.partials.folder-tree', ['nodes' => $node['children'], 'folderId' => $folderId])
            </div>
        @endif
    </div>
@endforeach
