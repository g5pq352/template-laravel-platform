<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CmsMenu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'parent_id',
        'location',
        'title',
        'type',
        'url',
        'route_name',
        'module_key',
        'icon',
        'target',
        'is_active',
        'sort_order',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('title');
    }

    public function pathLabel(): string
    {
        $labels = [];
        $current = $this;

        while ($current) {
            array_unshift($labels, $current->title);
            $current = $current->relationLoaded('parent') ? $current->parent : $current->parent()->first();
        }

        return implode(' / ', $labels);
    }
}
