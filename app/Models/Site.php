<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Site extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'default_locale',
        'timezone',
        'currency_code',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(Language::class)->orderByDesc('is_default')->orderBy('sort_order')->orderBy('id');
    }

    public function contentTypes(): HasMany
    {
        return $this->hasMany(ContentType::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class);
    }

    public function mediaFolders(): HasMany
    {
        return $this->hasMany(MediaFolder::class);
    }

    public function isMainSite(): bool
    {
        return $this->slug === config('cms.platform.main_site_slug', 'main-site');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('library')->useDisk('public');
    }
}
