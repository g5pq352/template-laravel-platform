<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class SiteFrontendProjectManager
{
    /**
     * @return array{created: bool, path: string|null, message: string|null}
     */
    public function provision(Site $site): array
    {
        if (app()->runningUnitTests() && trim((string) ($site->settings['frontend_template_path'] ?? '')) === '') {
            return ['created' => false, 'path' => null, 'message' => null];
        }

        $source = $this->templatePath($site);
        if (!$source || !is_dir($source)) {
            return ['created' => false, 'path' => null, 'message' => '找不到 Next 範本目錄。'];
        }

        $target = $this->targetPath($site, $source);
        File::ensureDirectoryExists($target);

        $this->copyDirectory($source, $target);
        $this->writeEnv($site, $target);
        $this->rememberProvisionResult($site, $source, $target);

        return ['created' => true, 'path' => $target, 'message' => null];
    }

    private function templatePath(Site $site): ?string
    {
        $configured = trim((string) ($site->settings['frontend_template_path'] ?? ''));
        if ($configured !== '') {
            return $this->normalizePath($configured);
        }

        $default = dirname(base_path()) . DIRECTORY_SEPARATOR . 'template-next-platform';

        return $this->normalizePath($default);
    }

    private function targetPath(Site $site, string $source): string
    {
        $configured = trim((string) ($site->settings['repository_path'] ?? ''));
        if ($configured !== '') {
            return $this->normalizePath($configured);
        }

        return dirname($source) . DIRECTORY_SEPARATOR . $site->slug . '-next-platform';
    }

    private function copyDirectory(string $source, string $target): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);
        $skip = ['.git', '.next', 'node_modules'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current) use ($skip): bool {
                    return !$current->isDir() || !in_array($current->getFilename(), $skip, true);
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);
                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($item->getPathname(), $destination);
        }
    }

    private function writeEnv(Site $site, string $target): void
    {
        $settings = Arr::wrap($site->settings ?? []);
        $token = trim((string) ($settings['api_access_token'] ?? ''));
        $apiBaseUrl = trim((string) ($settings['deployment']['api_base_url'] ?? config('app.url')));
        $language = $this->languageSlug($site);

        $content = implode("\n", [
            'API_BASE_URL=' . rtrim($apiBaseUrl, '/'),
            'API_SITE=' . $site->slug,
            'API_LANGUAGE=' . $language,
            'API_ACCESS_TOKEN=' . $token,
            '',
        ]);

        File::put($target . DIRECTORY_SEPARATOR . '.env.local', $content);
    }

    private function languageSlug(Site $site): string
    {
        $language = $site->languages()
            ->where('locale', $site->default_locale)
            ->orWhere(function ($query) use ($site): void {
                $query->where('site_id', $site->id)->where('is_default', true);
            })
            ->orderByDesc('is_default')
            ->first();

        return $language?->slug ?: 'tw';
    }

    private function rememberProvisionResult(Site $site, string $source, string $target): void
    {
        $settings = Arr::wrap($site->settings ?? []);
        $settings['repository_path'] = $target;
        $settings['frontend_template_path'] = $source;

        $deployment = Arr::wrap($settings['deployment'] ?? []);
        $deployment['frontend_project_path'] = $target;
        $deployment['frontend_generated_at'] = now()->format('Y-m-d H:i:s');
        $settings['deployment'] = $deployment;

        $site->forceFill(['settings' => $settings])->save();
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}
