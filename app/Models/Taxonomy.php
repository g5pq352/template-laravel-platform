<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $fillable = [
        'site_id',
        'code',
        'name',
        'is_hierarchical',
    ];

    protected function casts(): array
    {
        return [
            'is_hierarchical' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }
}
