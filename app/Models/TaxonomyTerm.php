<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxonomyTerm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'locale',
        'name',
        'slug',
        'description',
        'seo_title',
        'seo_description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function pathLabel(): string
    {
        $labels = [];
        $current = $this;

        while ($current) {
            array_unshift($labels, $current->name);
            $current = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();
        }

        return implode(' / ', $labels);
    }
}
