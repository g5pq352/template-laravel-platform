<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductTranslation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'locale',
        'name',
        'slug',
        'summary',
        'description',
        'seo_title',
        'seo_description',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
