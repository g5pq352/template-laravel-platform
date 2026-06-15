<?php

namespace App\Support;

use Illuminate\Support\Str;

class CmsSetLoader
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(?string $pageType = null): array
    {
        $modules = [];

        foreach (glob(self::path() . '/*Set.php') ?: [] as $file) {
            $config = require $file;
            if (!is_array($config)) {
                continue;
            }

            $config = CmsSetDefaults::normalize($config, $file);

            if ($pageType && ($config['pageType'] ?? null) !== $pageType) {
                continue;
            }

            $key = $config['resource'] ?? $config['adminKey'] ?? $config['module'] ?? self::keyFromFilename($file);
            $modules[$key] = $config;
        }

        ksort($modules);

        return $modules;
    }

    public static function get(string $key, ?string $pageType = null): ?array
    {
        return self::all($pageType)[$key] ?? null;
    }

    private static function path(): string
    {
        return config_path('cms/set');
    }

    private static function keyFromFilename(string $file): string
    {
        return Str::beforeLast(basename($file), 'Set.php');
    }
}
