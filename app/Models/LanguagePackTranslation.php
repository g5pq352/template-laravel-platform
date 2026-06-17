<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LanguagePackTranslation extends Model
{
    protected $fillable = [
        'language_pack_id',
        'locale',
        'value',
    ];

    public function languagePack(): BelongsTo
    {
        return $this->belongsTo(LanguagePack::class);
    }
}
