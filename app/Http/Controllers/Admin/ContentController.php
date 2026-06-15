<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentType;
use App\Support\AdminContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function index(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        $typeId = $request->integer('content_type_id') ?: null;
        $keyword = trim((string) $request->query('search', ''));

        $query = Content::query()
            ->with(['contentType', 'translations'])
            ->where('site_id', $site?->id);

        if ($typeId) {
            $query->where('content_type_id', $typeId);
        }

        if ($keyword !== '') {
            $query->whereHas('translations', function ($translationQuery) use ($keyword) {
                $translationQuery->where('title', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%");
            });
        }

        return view('admin.contents.index', [
            'site' => $site,
            'sites' => $context->sites(),
            'admin' => $context->user(),
            'contentTypes' => ContentType::query()->where('site_id', $site?->id)->orderBy('name')->get(),
            'contents' => $query->latest('updated_at')->paginate(15)->withQueryString(),
            'typeId' => $typeId,
            'keyword' => $keyword,
        ]);
    }

    public function create(AdminContext $context, Request $request): View
    {
        $site = $context->site();
        $typeId = $request->integer('content_type_id') ?: null;

        return view('admin.contents.form', [
            'site' => $site,
            'sites' => $context->sites(),
            'admin' => $context->user(),
            'content' => null,
            'translation' => null,
            'contentTypes' => ContentType::query()->where('site_id', $site?->id)->orderBy('name')->get(),
            'defaultTypeId' => $typeId,
        ]);
    }

    public function store(AdminContext $context, Request $request): RedirectResponse
    {
        $site = $context->site();
        $data = $this->validatedData($request, $site?->id);
        $slug = $data['slug'] ?: Str::slug($data['title']);

        $content = Content::query()->create([
            'site_id' => $site->id,
            'content_type_id' => $data['content_type_id'],
            'status' => $data['status'],
            'is_pinned' => $request->boolean('is_pinned'),
            'sort_order' => (int) $data['sort_order'],
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        $content->translations()->create([
            'locale' => $data['locale'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($slug, $data['locale']),
            'summary' => $data['summary'],
            'body' => $data['body'],
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
        ]);

        return redirect()->route('admin.contents.index', ['content_type_id' => $content->content_type_id])->with('status', '內容已建立。');
    }

    public function edit(AdminContext $context, Content $content): View
    {
        $site = $context->site();
        abort_unless($content->site_id === $site?->id, 404);
        $content->load('translations');

        return view('admin.contents.form', [
            'site' => $site,
            'sites' => $context->sites(),
            'admin' => $context->user(),
            'content' => $content,
            'translation' => $content->translation($site->default_locale),
            'contentTypes' => ContentType::query()->where('site_id', $site->id)->orderBy('name')->get(),
        ]);
    }

    public function update(AdminContext $context, Request $request, Content $content): RedirectResponse
    {
        $site = $context->site();
        abort_unless($content->site_id === $site?->id, 404);

        $data = $this->validatedData($request, $site->id, $content->id);
        $slug = $data['slug'] ?: Str::slug($data['title']);

        $content->update([
            'content_type_id' => $data['content_type_id'],
            'status' => $data['status'],
            'is_pinned' => $request->boolean('is_pinned'),
            'sort_order' => (int) $data['sort_order'],
            'published_at' => $data['status'] === 'published' ? ($content->published_at ?? now()) : null,
        ]);

        $translation = $content->translations()->where('locale', $data['locale'])->first();
        $translationData = [
            'locale' => $data['locale'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($slug, $data['locale'], $translation?->id),
            'summary' => $data['summary'],
            'body' => $data['body'],
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
        ];

        if ($translation) {
            $translation->update($translationData);
        } else {
            $content->translations()->create($translationData);
        }

        return redirect()->route('admin.contents.edit', $content)->with('status', '內容已更新。');
    }

    public function destroy(AdminContext $context, Content $content): RedirectResponse
    {
        $site = $context->site();
        abort_unless($content->site_id === $site?->id, 404);

        $content->delete();

        return redirect()->route('admin.contents.index', ['content_type_id' => $content->content_type_id])->with('status', '內容已移至封存。');
    }

    private function validatedData(Request $request, ?int $siteId, ?int $contentId = null): array
    {
        return $request->validate([
            'content_type_id' => [
                'required',
                'integer',
                Rule::exists('content_types', 'id')->where('site_id', $siteId),
            ],
            'locale' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'scheduled', 'hidden', 'archived'])],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }

    private function uniqueSlug(string $slug, string $locale, ?int $ignoreTranslationId = null): string
    {
        $baseSlug = Str::slug($slug) ?: Str::random(8);
        $candidate = $baseSlug;
        $counter = 2;

        while (
            \App\Models\ContentTranslation::query()
                ->where('locale', $locale)
                ->where('slug', $candidate)
                ->when($ignoreTranslationId, fn ($query) => $query->whereKeyNot($ignoreTranslationId))
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }
}
