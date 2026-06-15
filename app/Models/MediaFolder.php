<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaFolder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'parent_id',
        'name',
        'slug',
        'sort_order',
    ];

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
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
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
