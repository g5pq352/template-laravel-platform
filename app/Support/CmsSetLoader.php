<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CmsSetLoader
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(?string $pageType = null, ?Site $site = null): array
    {
        return self::loadFromPaths(self::paths($site), $pageType);
    }

    /**
     * Load every known site set file. This is used only while registering
     * named admin routes, so site-specific custom modules can keep the same
     * admin.{resource}.* route pattern as shared modules.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allKnown(?string $pageType = null): array
    {
        return self::loadFromPaths(self::knownPaths(), $pageType);
    }

    /**
     * @param list<string> $paths
     * @return array<string, array<string, mixed>>
     */
    private static function loadFromPaths(array $paths, ?string $pageType = null): array
    {
        $modules = [];
        $sharedPath = self::sharedPath();
        $sharedSetModules = self::sharedSetModules();

        foreach ($paths as $path) {
            foreach (glob($path . '/*Set.php') ?: [] as $file) {
                $config = require $file;
                if (!is_array($config)) {
                    continue;
                }

                $config = CmsSetDefaults::normalize($config, $file);

                if ($pageType && ($config['pageType'] ?? null) !== $pageType) {
                    continue;
                }

                $key = $config['resource'] ?? $config['adminKey'] ?? $config['module'] ?? self::keyFromFilename($file);
                if ($path !== $sharedPath && in_array($key, $sharedSetModules, true)) {
                    continue;
                }

                $modules[$key] = $config;
            }
        }

        ksort($modules);

        return $modules;
    }

    public static function get(string $key, ?string $pageType = null, ?Site $site = null): ?array
    {
        return self::all($pageType, $site)[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    private static function paths(?Site $site = null): array
    {
        $paths = [self::sharedPath()];
        $site ??= self::currentSite();
        $sitePath = self::siteSetPath($site);

        if ($sitePath && is_dir($sitePath)) {
            $paths[] = $sitePath;
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private static function knownPaths(): array
    {
        $paths = [self::sharedPath()];

        try {
            if (Schema::hasTable('sites')) {
                Site::query()
                    ->whereNotNull('settings')
                    ->get()
                    ->each(function (Site $site) use (&$paths): void {
                        $sitePath = self::siteSetPath($site);
                        if ($sitePath && is_dir($sitePath)) {
                            $paths[] = $sitePath;
                        }
                    });
            }
        } catch (\Throwable) {
            return array_values(array_unique($paths));
        }

        return array_values(array_unique($paths));
    }

    private static function sharedPath(): string
    {
        return config_path('cms/set');
    }

    private static function siteSetPath(?Site $site): ?string
    {
        $path = trim((string) ($site?->settings['cms_set_path'] ?? ''));

        if ($path === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) || str_starts_with($path, '/')) {
            return rtrim($path, "\\/");
        }

        return rtrim(base_path($path), "\\/");
    }

    private static function currentSite(): ?Site
    {
        try {
            if (!app()->bound('request')) {
                return null;
            }

            $request = request();
            $site = $request->attributes->get('site');
            if ($site instanceof Site) {
                return $site;
            }

            if (!$request->hasSession()) {
                return null;
            }

            $siteId = $request->session()->get('current_site_id');

            return $siteId ? Site::query()->find($siteId) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private static function sharedSetModules(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $module): string => trim((string) $module),
            config('cms.platform.shared_set_modules', [])
        )));
    }

    private static function keyFromFilename(string $file): string
    {
        return Str::beforeLast(basename($file), 'Set.php');
    }
}
