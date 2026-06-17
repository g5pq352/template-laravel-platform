<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Support\AdminContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaLibraryController extends Controller
{
    public function index(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        abort_unless($site, 404);

        $trash = $request->boolean('trash');
        $folderId = $request->integer('folder_id') ?: null;
        $keyword = trim((string) $request->query('keyword', ''));
        $sort = (string) $request->query('sort', 'date_desc');
        $perPage = min(max((int) $request->query('per_page', 24), 12), 96);

        $folder = $folderId
            ? ($trash
                ? MediaFolder::withTrashed()->where('site_id', $site->id)->findOrFail($folderId)
                : MediaFolder::query()->where('site_id', $site->id)->with('parent')->findOrFail($folderId))
            : null;

        $folders = $trash
            ? $this->trashedFolders($site->id, $folderId)
            : MediaFolder::query()
                ->where('site_id', $site->id)
                ->where(function ($query) use ($folderId): void {
                    $folderId ? $query->where('parent_id', $folderId) : $query->whereNull('parent_id');
                })
                ->withCount([
                    'children as active_children_count' => fn ($query) => $query->whereNull('deleted_at'),
                    'media as active_media_count' => fn ($query) => $query->whereNull('deleted_at'),
                ])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $mediaQuery = Media::query()
            ->where('site_id', $site->id)
            ->where('collection_name', 'library');

        if ($trash) {
            $mediaQuery->onlyTrashed();

            if ($folderId) {
                $mediaQuery->where('folder_id', $folderId);
            } else {
                $mediaQuery->where(function ($query) use ($site): void {
                    $query->whereNull('folder_id')
                        ->orWhereNotIn('folder_id', MediaFolder::onlyTrashed()
                            ->where('site_id', $site->id)
                            ->select('id'));
                });
            }
        } else {
            $mediaQuery->where(function ($query) use ($folderId): void {
                $folderId ? $query->where('folder_id', $folderId) : $query->whereNull('folder_id');
            })->whereNull('deleted_at');
        }

        $mediaQuery->when($keyword !== '', function ($query) use ($keyword): void {
            $query->where(function ($nested) use ($keyword): void {
                $nested->where('name', 'like', "%{$keyword}%")
                    ->orWhere('file_name', 'like', "%{$keyword}%")
                    ->orWhere('alt_text', 'like', "%{$keyword}%");
            });
        });

        match ($sort) {
            'name_asc' => $mediaQuery->orderBy('name')->orderBy('id'),
            'name_desc' => $mediaQuery->orderByDesc('name')->orderByDesc('id'),
            'date_asc' => $mediaQuery->orderBy('created_at')->orderBy('id'),
            default => $mediaQuery->orderByDesc('created_at')->orderByDesc('id'),
        };

        return view('admin.media-library.index', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'folder' => $folder,
            'folders' => $folders,
            'folderTree' => $this->folderTree($site->id),
            'mediaItems' => $mediaQuery->paginate($perPage)->withQueryString(),
            'folderId' => $folderId,
            'keyword' => $keyword,
            'sort' => $sort,
            'trash' => $trash,
            'trashCount' => $this->trashCount($site->id),
        ]);
    }

    public function storeFolder(AdminContext $context, Request $request): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $data = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('site_id', $site->id),
            ],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $parentId = $data['parent_id'] ?? null;

        MediaFolder::query()->create([
            'site_id' => $site->id,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $this->uniqueFolderSlug($site->id, $parentId, $data['name']),
            'sort_order' => ((int) MediaFolder::query()
                ->where('site_id', $site->id)
                ->where('parent_id', $parentId)
                ->max('sort_order')) + 1,
        ]);

        return back();
    }

    public function updateFolder(AdminContext $context, Request $request, MediaFolder $folder): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $folder->site_id === (int) $site->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update([
            'name' => $data['name'],
            'slug' => $this->uniqueFolderSlug($site->id, $folder->parent_id, $data['name'], $folder->id),
        ]);

        return back();
    }

    public function destroyFolder(AdminContext $context, MediaFolder $folder): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $folder->site_id === (int) $site->id, 404);

        $parentId = $folder->parent_id;
        $folderIds = $this->descendantFolderIds($folder);

        DB::transaction(function () use ($site, $folderIds): void {
            DB::table('media')
                ->where('site_id', $site->id)
                ->whereIn('folder_id', $folderIds)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            MediaFolder::query()
                ->where('site_id', $site->id)
                ->whereIn('id', $folderIds)
                ->delete();
        });

        return redirect()->route('admin.media-library.index', array_filter(['folder_id' => $parentId]));
    }

    public function restoreFolder(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $folder = MediaFolder::withTrashed()
            ->where('site_id', $site->id)
            ->findOrFail($id);

        $folderIds = $this->descendantFolderIdsWithTrashed($folder);

        DB::transaction(function () use ($site, $folder, $folderIds): void {
            if ($folder->parent_id && MediaFolder::onlyTrashed()
                ->where('site_id', $site->id)
                ->whereKey($folder->parent_id)
                ->exists()) {
                $folder->forceFill(['parent_id' => null])->save();
            }

            MediaFolder::withTrashed()
                ->where('site_id', $site->id)
                ->whereIn('id', $folderIds)
                ->restore();

            DB::table('media')
                ->where('site_id', $site->id)
                ->whereIn('folder_id', $folderIds)
                ->whereNotNull('deleted_at')
                ->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        });

        return back();
    }

    public function forceDeleteFolder(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $folder = MediaFolder::withTrashed()
            ->where('site_id', $site->id)
            ->findOrFail($id);

        $folderIds = $this->descendantFolderIdsWithTrashed($folder);

        DB::transaction(function () use ($site, $folderIds): void {
            Media::withTrashed()
                ->where('site_id', $site->id)
                ->whereIn('folder_id', $folderIds)
                ->get()
                ->each(fn (Media $media) => $media->forceDelete());

            foreach (array_reverse($folderIds) as $folderId) {
                MediaFolder::withTrashed()
                    ->where('site_id', $site->id)
                    ->whereKey($folderId)
                    ->first()
                    ?->forceDelete();
            }
        });

        return back();
    }

    public function storeMedia(AdminContext $context, Request $request): RedirectResponse|JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $data = $request->validate([
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('site_id', $site->id),
            ],
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
        ]);

        $uploaded = [];

        foreach ($request->file('images', []) as $file) {
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $media = $site
                ->addMedia($file)
                ->usingName($name)
                ->withCustomProperties([
                    'uploaded_from' => 'cms_library',
                    'folder_id' => $data['folder_id'] ?? null,
                ])
                ->toMediaCollection('library', 'public');

            DB::table('media')->where('id', $media->id)->update([
                'site_id' => $site->id,
                'folder_id' => $data['folder_id'] ?? null,
                'alt_text' => $name,
                'updated_at' => now(),
            ]);

            $uploaded[] = $this->mediaPayload($media->fresh());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'items' => $uploaded,
            ]);
        }

        return back();
    }

    public function updateMedia(AdminContext $context, Request $request, Media $media): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $media->site_id === (int) $site->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('site_id', $site->id),
            ],
        ]);

        $media->forceFill([
            'name' => $data['name'],
            'alt_text' => $data['alt_text'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
        ])->save();

        return back();
    }

    public function moveMedia(AdminContext $context, Request $request, Media $media): JsonResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $media->site_id === (int) $site->id, 404);

        $data = $request->validate([
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('media_folders', 'id')->where('site_id', $site->id),
            ],
        ]);

        $media->forceFill([
            'folder_id' => $data['folder_id'] ?? null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    public function destroyMedia(AdminContext $context, Media $media): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $media->site_id === (int) $site->id, 404);

        DB::table('media')->where('id', $media->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return back();
    }

    public function bulkAction(AdminContext $context, Request $request): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['destroy', 'restore', 'force_delete'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));

        if ($data['action'] === 'destroy') {
            DB::table('media')
                ->where('site_id', $site->id)
                ->where('collection_name', 'library')
                ->whereIn('id', $ids)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);

            return back();
        }

        if ($data['action'] === 'restore') {
            DB::transaction(function () use ($site, $ids): void {
                Media::withTrashed()
                    ->where('site_id', $site->id)
                    ->where('collection_name', 'library')
                    ->whereIn('id', $ids)
                    ->get()
                    ->each(function (Media $media) use ($site): void {
                        $this->restoreMediaWithFolder($site->id, $media);
                    });
            });

            return back();
        }

        Media::withTrashed()
            ->where('site_id', $site->id)
            ->where('collection_name', 'library')
            ->whereIn('id', $ids)
            ->get()
            ->each(fn (Media $media) => $media->forceDelete());

        return back();
    }

    public function restoreMedia(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $media = Media::withTrashed()
            ->where('site_id', $site->id)
            ->findOrFail($id);

        DB::transaction(function () use ($site, $media): void {
            $this->restoreMediaWithFolder($site->id, $media);
        });

        return back();
    }

    public function forceDeleteMedia(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $media = Media::withTrashed()
            ->where('site_id', $site->id)
            ->findOrFail($id);

        $media->forceDelete();

        return back();
    }

    public function picker(AdminContext $context, Request $request): JsonResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $folderId = $request->integer('folder_id') ?: null;
        $keyword = trim((string) $request->query('keyword', ''));

        $items = Media::query()
            ->where('site_id', $site->id)
            ->where('collection_name', 'library')
            ->where(function ($query) use ($folderId): void {
                $folderId ? $query->where('folder_id', $folderId) : $query->whereNull('folder_id');
            })
            ->whereNull('deleted_at')
            ->when($keyword !== '', fn ($query) => $query->where('name', 'like', "%{$keyword}%"))
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->map(fn (Media $media) => $this->mediaPayload($media));

        return response()->json([
            'folders' => $this->folderTree($site->id),
            'items' => $items,
        ]);
    }

    private function mediaPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'alt_text' => $media->alt_text,
            'mime_type' => $media->mime_type,
            'size' => $media->human_readable_size,
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
            'url' => $media->getUrl(),
        ];
    }

    private function folderTree(int $siteId): array
    {
        $folders = MediaFolder::query()
            ->where('site_id', $siteId)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $build = function ($parentId = null) use (&$build, $folders): array {
            return $folders
                ->where('parent_id', $parentId)
                ->values()
                ->map(fn (MediaFolder $folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'children' => $build($folder->id),
                ])
                ->all();
        };

        return $build();
    }

    private function trashedFolders(int $siteId, ?int $parentId = null): Collection
    {
        $trashedFolderIds = MediaFolder::onlyTrashed()
            ->where('site_id', $siteId)
            ->pluck('id');

        $query = MediaFolder::onlyTrashed()
            ->where('site_id', $siteId)
            ->withCount([
                'children as active_children_count' => fn ($query) => $query->withTrashed(),
                'media as active_media_count' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('deleted_at')
            ->orderBy('name');

        if ($parentId) {
            return $query->where('parent_id', $parentId)->get();
        }

        return $query
            ->where(function ($query) use ($trashedFolderIds): void {
                $query->whereNull('parent_id');

                if ($trashedFolderIds->isNotEmpty()) {
                    $query->orWhereNotIn('parent_id', $trashedFolderIds);
                }
            })
            ->get();
    }

    private function trashCount(int $siteId): int
    {
        $mediaCount = Media::withTrashed()
            ->where('site_id', $siteId)
            ->where('collection_name', 'library')
            ->whereNotNull('deleted_at')
            ->count();

        $folderCount = MediaFolder::onlyTrashed()
            ->where('site_id', $siteId)
            ->count();

        return $mediaCount + $folderCount;
    }

    /**
     * @return array<int>
     */
    private function descendantFolderIds(MediaFolder $folder): array
    {
        $ids = [$folder->id];
        $childIds = MediaFolder::query()
            ->where('site_id', $folder->site_id)
            ->where('parent_id', $folder->id)
            ->pluck('id');

        foreach ($childIds as $childId) {
            $child = MediaFolder::query()->find($childId);
            if ($child) {
                array_push($ids, ...$this->descendantFolderIds($child));
            }
        }

        return $ids;
    }

    /**
     * @return array<int>
     */
    private function descendantFolderIdsWithTrashed(MediaFolder $folder): array
    {
        $ids = [$folder->id];
        $childIds = MediaFolder::withTrashed()
            ->where('site_id', $folder->site_id)
            ->where('parent_id', $folder->id)
            ->pluck('id');

        foreach ($childIds as $childId) {
            $child = MediaFolder::withTrashed()->find($childId);
            if ($child) {
                array_push($ids, ...$this->descendantFolderIdsWithTrashed($child));
            }
        }

        return $ids;
    }

    private function restoreMediaWithFolder(int $siteId, Media $media): void
    {
        $folderId = $media->folder_id;

        if ($folderId) {
            $folderId = $this->restoreFolderChainForMedia($siteId, (int) $folderId);
        }

        DB::table('media')->where('id', $media->id)->update([
            'folder_id' => $folderId,
            'deleted_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function restoreFolderChainForMedia(int $siteId, int $folderId): ?int
    {
        $chain = [];
        $current = MediaFolder::withTrashed()
            ->where('site_id', $siteId)
            ->find($folderId);

        while ($current) {
            $chain[] = $current;

            if (!$current->parent_id) {
                break;
            }

            $parent = MediaFolder::withTrashed()
                ->where('site_id', $siteId)
                ->find($current->parent_id);

            if (!$parent) {
                $current->forceFill(['parent_id' => null])->save();
                break;
            }

            $current = $parent;
        }

        if (empty($chain)) {
            return null;
        }

        foreach (array_reverse($chain) as $folder) {
            if ($folder->trashed()) {
                $folder->restore();
            }
        }

        return $folderId;
    }

    private function uniqueFolderSlug(int $siteId, ?int $parentId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $index = 2;

        while (MediaFolder::withTrashed()
            ->where('site_id', $siteId)
            ->where('parent_id', $parentId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
