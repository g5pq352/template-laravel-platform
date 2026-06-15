<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;

class TaxonomyTreeService
{
    public function taxonomyForSite(Site $site, string $code, ?string $label = null, bool $isHierarchical = true): Taxonomy
    {
        $taxonomy = Taxonomy::query()->firstOrNew(['site_id' => $site->id, 'code' => $code]);

        if (!$taxonomy->exists) {
            $taxonomy->name = $label ?? $code;
        }

        $taxonomy->is_hierarchical = $isHierarchical;
        $taxonomy->save();

        if (!$isHierarchical) {
            $taxonomy->terms()->whereNotNull('parent_id')->update(['parent_id' => null]);
        }

        return $taxonomy;
    }

    public function flattenedOptions(Taxonomy $taxonomy, ?string $locale = null, ?int $excludeId = null): Collection
    {
        $terms = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->id)
            ->where('locale', $locale ?? $taxonomy->site?->default_locale ?? app()->getLocale())
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $excluded = $excludeId ? $this->descendantIds($terms, $excludeId)->push($excludeId)->all() : [];

        return $terms
            ->reject(fn (TaxonomyTerm $term) => in_array($term->id, $excluded, true))
            ->map(fn (TaxonomyTerm $term) => [
                'id' => $term->id,
                'label' => $term->pathLabel(),
            ])
            ->values();
    }

    private function descendantIds(Collection $terms, int $parentId): Collection
    {
        $children = $terms->where('parent_id', $parentId);

        return $children->pluck('id')->merge(
            $children->flatMap(fn (TaxonomyTerm $term) => $this->descendantIds($terms, $term->id))
        )->values();
    }
}
