<?php

namespace App\Support;

use App\Models\Content;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ApiMediaFormatter
{
    public function normalizeCustomFields(array $customFields): array
    {
        return $this->normalizeValue($customFields);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaFor(Model $item, string $strategy): array
    {
        $pivot = $this->pivotFor($item, $strategy);
        if (!$pivot || !Schema::hasTable('media_files') || !Schema::hasTable($pivot['table'])) {
            return [];
        }

        return DB::table($pivot['table'])
            ->join('media_files', 'media_files.id', '=', "{$pivot['table']}.media_file_id")
            ->where("{$pivot['table']}.{$pivot['foreign_key']}", $item->getKey())
            ->orderBy("{$pivot['table']}.sort_order")
            ->orderBy('media_files.id')
            ->select([
                'media_files.id',
                'media_files.disk',
                'media_files.path',
                'media_files.original_name',
                'media_files.mime_type',
                'media_files.size_bytes',
                'media_files.width',
                'media_files.height',
                'media_files.alt_text',
                "{$pivot['table']}.role",
                "{$pivot['table']}.sort_order",
            ])
            ->get()
            ->map(fn ($media) => $this->mediaPayload($media))
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $media
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function groupByRole(array $media): array
    {
        return collect($media)
            ->groupBy('role')
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        if (isset($normalized['path']) && is_string($normalized['path']) && $normalized['path'] !== '') {
            $disk = is_string($normalized['disk'] ?? null) ? $normalized['disk'] : 'public';
            $normalized['url'] = $this->storageUrl($disk, $normalized['path']);
        }

        return $normalized;
    }

    private function mediaPayload(object $media): array
    {
        $disk = is_string($media->disk ?? null) && $media->disk !== '' ? $media->disk : 'public';

        return [
            'id' => (int) $media->id,
            'role' => $media->role,
            'title' => $media->alt_text ?? '',
            'alt' => $media->alt_text ?? '',
            'url' => $this->storageUrl($disk, (string) $media->path),
            'disk' => $disk,
            'path' => $media->path,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => $media->size_bytes !== null ? (int) $media->size_bytes : null,
            'width' => $media->width !== null ? (int) $media->width : null,
            'height' => $media->height !== null ? (int) $media->height : null,
            'sort_order' => $media->sort_order !== null ? (int) $media->sort_order : null,
        ];
    }

    private function storageUrl(string $disk, string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * @return array{table:string, foreign_key:string}|null
     */
    private function pivotFor(Model $item, string $strategy): ?array
    {
        if ($item instanceof Content || $strategy === 'content') {
            return ['table' => 'content_media', 'foreign_key' => 'content_id'];
        }

        if ($item instanceof Product || $strategy === 'product') {
            return ['table' => 'product_media', 'foreign_key' => 'product_id'];
        }

        return null;
    }
}
