<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentTranslation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'content_id',
        'locale',
        'title',
        'slug',
        'summary',
        'body',
        'seo_title',
        'seo_description',
        'custom_fields',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'view_count' => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }
}
