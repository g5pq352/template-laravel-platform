<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Support\AdminContext;
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
        $sort = $request->query('sort', 'date_desc');

        $folder = $folderId
            ? MediaFolder::query()->where('site_id', $site->id)->with('parent')->findOrFail($folderId)
            : null;

        $folders = MediaFolder::query()
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
            ->where('collection_name', 'library')
            ->where(function ($query) use ($folderId): void {
                $folderId ? $query->where('folder_id', $folderId) : $query->whereNull('folder_id');
            });

        $trash ? $mediaQuery->onlyTrashed() : $mediaQuery->whereNull('deleted_at');

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

        $mediaItems = $mediaQuery
            ->paginate((int) $request->query('per_page', 24))
            ->withQueryString();

        return view('admin.media-library.index', [
            'admin' => $context->user(),
            'site' => $site,
            'sites' => $context->sites(),
            'folder' => $folder,
            'folders' => $folders,
            'folderTree' => $this->folderTree($site->id),
            'mediaItems' => $mediaItems,
            'folderId' => $folderId,
            'keyword' => $keyword,
            'sort' => $sort,
            'trash' => $trash,
            'trashCount' => Media::withTrashed()
                ->where('site_id', $site->id)
                ->where('collection_name', 'library')
                ->whereNotNull('deleted_at')
                ->count(),
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
        $slug = $this->uniqueFolderSlug($site->id, $parentId, $data['name']);

        MediaFolder::query()->create([
            'site_id' => $site->id,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => ((int) MediaFolder::query()
                ->where('site_id', $site->id)
                ->where('parent_id', $parentId)
                ->max('sort_order')) + 1,
        ]);

        return back()->with('status', '資料夾已建立');
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

        return back()->with('status', '資料夾已重新命名');
    }

    public function destroyFolder(AdminContext $context, MediaFolder $folder): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $folder->site_id === (int) $site->id, 404);

        $hasChildren = MediaFolder::query()
            ->where('site_id', $site->id)
            ->where('parent_id', $folder->id)
            ->exists();
        $hasMedia = Media::query()
            ->where('site_id', $site->id)
            ->where('folder_id', $folder->id)
            ->exists();

        if ($hasChildren || $hasMedia) {
            return back()->with('error', '資料夾內還有資料，請先移動或刪除裡面的圖片與子資料夾');
        }

        $parentId = $folder->parent_id;
        $folder->delete();

        return redirect()
            ->route('admin.media-library.index', array_filter(['folder_id' => $parentId]))
            ->with('status', '資料夾已刪除');
    }

    public function storeMedia(AdminContext $context, Request $request): RedirectResponse
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

        foreach ($request->file('images', []) as $file) {
            $media = $site
                ->addMedia($file)
                ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                ->withCustomProperties([
                    'uploaded_from' => 'cms_library',
                    'folder_id' => $data['folder_id'] ?? null,
                ])
                ->toMediaCollection('library', 'public');

            DB::table('media')->where('id', $media->id)->update([
                'site_id' => $site->id,
                'folder_id' => $data['folder_id'] ?? null,
                'alt_text' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', '圖片已上傳');
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

        return back()->with('status', '圖片資料已更新');
    }

    public function destroyMedia(AdminContext $context, Media $media): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site && (int) $media->site_id === (int) $site->id, 404);

        DB::table('media')->where('id', $media->id)->update([
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', '圖片已移到垃圾桶');
    }

    public function restoreMedia(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        DB::table('media')
            ->where('id', $id)
            ->where('site_id', $site->id)
            ->update([
                'deleted_at' => null,
                'updated_at' => now(),
            ]);

        return back()->with('status', '圖片已還原');
    }

    public function forceDeleteMedia(AdminContext $context, int $id): RedirectResponse
    {
        $site = $context->site();
        abort_unless($site, 404);

        $media = Media::withTrashed()
            ->where('site_id', $site->id)
            ->findOrFail($id);

        $media->forceDelete();

        return back()->with('status', '圖片已永久刪除');
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
