<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AdminLanguage
{
    /**
     * @return array{languages: Collection<int, Language>, current: Language, locale: string, slug: string}
     */
    public static function context(?Site $site, Request $request): array
    {
        $languages = self::activeLanguages($site);
        $default = $languages->firstWhere('is_default', true) ?? $languages->first();
        $requested = $request->query('language') ?: $request->session()->get('admin_editing_language');
        $current = $languages->first(fn (Language $language) => in_array($requested, [$language->slug, $language->locale], true))
            ?? $default;

        if ($current) {
            $request->session()->put('admin_editing_language', $current->slug);
        }

        return [
            'languages' => $languages,
            'current' => $current,
            'locale' => $current->locale,
            'slug' => $current->slug,
        ];
    }

    /**
     * @return Collection<int, Language>
     */
    public static function activeLanguages(?Site $site): Collection
    {
        if (!$site || !Schema::hasTable('languages')) {
            return collect([self::fallbackLanguage($site)]);
        }

        $languages = Language::query()
            ->where('site_id', $site->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $languages->isNotEmpty() ? $languages : collect([self::fallbackLanguage($site)]);
    }

    public static function fallbackLanguage(?Site $site): Language
    {
        $locale = $site?->default_locale ?: app()->getLocale();

        return new Language([
            'site_id' => $site?->id,
            'name' => '繁體中文',
            'name_en' => 'Traditional Chinese',
            'slug' => $locale,
            'locale' => $locale,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public static function enabledFor(array $config): bool
    {
        if (($config['hasLanguage'] ?? null) === false || ($config['languageEnabled'] ?? null) === false) {
            return false;
        }

        if (($config['hasLanguage'] ?? null) === true || ($config['languageEnabled'] ?? null) === true) {
            return true;
        }

        return in_array($config['strategy'] ?? null, ['content', 'product', 'taxonomy', 'contact'], true);
    }
}
