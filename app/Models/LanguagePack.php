<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LanguagePack extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'site_id',
        'key',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LanguagePackTranslation::class);
    }

    public function translationValue(string $locale): ?string
    {
        return $this->translations->firstWhere('locale', $locale)?->value;
    }
}
