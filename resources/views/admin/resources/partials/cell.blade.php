@php
    $key = $column['key'];
    $type = $column['type'] ?? 'text';
    $translation = method_exists($item, 'translation') ? $item->translation($site?->default_locale) : null;
    $relation = $resourceConfig['category_relation'] ?? null;
    $categories = $relation && isset($item->{$relation}) ? $item->{$relation} : collect();
    $value = match ($key) {
        'title' => $translation?->title,
        'name' => $translation?->name ?? $item->name ?? null,
        'categories' => $categories->map(fn($term) => $term->pathLabel())->implode(' / '),
        'published_at', 'updated_at', 'created_at' => optional($item->{$key} ?? $item->created_at)->format('Y-m-d H:i:s'),
        'base_price' => isset($item->{$key}) ? number_format((float) $item->{$key}) : null,
        'is_read' => $item->is_read ? '已讀' : '未讀',
        'view_count' => $item->view_count ?? 0,
        default => $item->{$key} ?? null,
    };

    $statusClass = match ((string) $value) {
        'published', 'active', 'completed' => 'success',
        'draft', 'pending' => 'warning',
        'hidden', 'archived', 'cancelled' => 'danger',
        'processing', 'scheduled' => 'info',
        default => 'secondary',
    };
@endphp

@if($type === 'sort')
    <select class="form-control-sm js-row-sort" style="width: 55px;" data-url="{{ route("admin.{$resource}.sort", $item) }}" data-term-id="{{ $termId ?? '' }}">
        @for($i = 1; $i <= max(1, $sortOptionCount ?? $items?->total() ?? 0); $i++)
            <option value="{{ $i }}" @selected((int) ($value ?? 1) === $i)>{{ $i }}</option>
        @endfor
    </select>
@elseif($type === 'pin')
    <button class="btn {{ $item->is_pinned ? 'btn-warning' : 'btn-default' }} js-row-pin" type="button" title="{{ $item->is_pinned ? '取消置頂' : '置頂' }}" data-url="{{ route("admin.{$resource}.toggle-pin", $item) }}">
        <i class="fas fa-thumbtack"></i>
    </button>
@elseif($type === 'image')
    @php
        $imageRole = $column['file_type'] ?? $resourceConfig['list_image_type'] ?? null;
        $pivotTable = ($resourceConfig['strategy'] ?? null) === 'product' ? 'product_media' : 'content_media';
        $foreignKey = ($resourceConfig['strategy'] ?? null) === 'product' ? 'product_id' : 'content_id';
        $media = $imageRole && in_array($resourceConfig['strategy'] ?? null, ['content', 'product'], true)
            ? \Illuminate\Support\Facades\DB::table($pivotTable)
                ->join('media_files', 'media_files.id', '=', "{$pivotTable}.media_file_id")
                ->where("{$pivotTable}.{$foreignKey}", $item->id)
                ->where("{$pivotTable}.role", $imageRole)
                ->orderBy("{$pivotTable}.sort_order")
                ->select(['media_files.path', 'media_files.alt_text'])
                ->first()
            : null;
    @endphp
    @if($media)
        @php($imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($media->path))
        @if(!($trash ?? false))
        <a href="{{ route("admin.{$resource}.edit", $item) }}" title="編輯">
            <img src="{{ $imageUrl }}" alt="{{ $media->alt_text ?? '' }}" style="width: 100px; height: 66px; object-fit: cover;">
        </a>
        @else
            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($media->path) }}" alt="{{ $media->alt_text ?? '' }}" style="width: 100px; height: 66px; object-fit: cover;">
        @endif
    @else
        @if(!($trash ?? false))
            <a href="{{ route("admin.{$resource}.edit", $item) }}" class="d-inline-flex align-items-center justify-content-center text-white text-decoration-none" style="width: 100px; height: 66px; background: #8c8c8c;">
                無提供照片
            </a>
        @else
            <div class="d-inline-flex align-items-center justify-content-center text-white" style="width: 100px; height: 66px; background: #8c8c8c;">
                無提供照片
            </div>
        @endif
    @endif
@elseif($key === 'status')
    <span class="btn js-row-status" data-url="{{ route("admin.{$resource}.toggle-status", $item) }}" style="background-color: {{ $statusClass === 'danger' ? '#dc3545' : ($statusClass === 'warning' ? '#ffc107' : ($statusClass === 'info' ? '#17a2b8' : '#28a745')) }}; cursor: pointer; padding: 5px 10px; border-radius: 4px; color: white;">{{ $resourceConfig['status_options'][$value] ?? $value }}</span>
@elseif($key === 'is_read')
    <span class="btn badge" style="background-color: {{ $item->is_read ? '#28a745' : '#dc3545' }}; padding: 10px 10px; border-radius: 4px; color: white;">{{ $value }}</span>
@elseif(in_array($key, ['title', 'name', 'subject'], true))
    <a class="font-weight-semibold" href="{{ route("admin.{$resource}.edit", $item) }}">{{ $value ?: '未命名' }}</a>
    @if($translation?->summary)
        <div class="text-muted text-2 mt-1">{{ $translation->summary }}</div>
    @endif
@else
    {{ $value !== null && $value !== '' ? $value : '-' }}
@endif
