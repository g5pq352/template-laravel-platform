<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SiteGitLabRepositoryManager
{
    /**
     * @return array{ok: bool, url: string|null, message: string, data?: array<string, mixed>}
     */
    public function ensureProject(Site $site): array
    {
        $configured = trim((string) ($site->settings['deployment']['git_repository_url'] ?? ''));
        if ($configured !== '') {
            return ['ok' => true, 'url' => $configured, 'message' => '已使用既有 Git Repository URL。'];
        }

        $token = trim((string) config('cms.platform.gitlab.token', ''));
        if ($token === '') {
            return ['ok' => false, 'url' => null, 'message' => '尚未設定 CMS_GITLAB_TOKEN，無法自動建立 GitLab 專案。'];
        }

        $baseUrl = rtrim((string) config('cms.platform.gitlab.url', 'https://gitlab.com'), '/');
        $payload = [
            'name' => $site->name ?: $site->slug,
            'path' => $site->slug,
            'visibility' => (string) config('cms.platform.gitlab.visibility', 'private'),
        ];

        $namespaceId = trim((string) config('cms.platform.gitlab.namespace_id', ''));
        if ($namespaceId !== '') {
            $payload['namespace_id'] = $namespaceId;
        }

        $response = Http::withHeaders(['PRIVATE-TOKEN' => $token])
            ->acceptJson()
            ->post($baseUrl . '/api/v4/projects', $payload);

        if ($response->successful()) {
            return $this->projectResult($site, $response->json() ?: [], 'GitLab 專案已建立。');
        }

        $existing = $this->findExistingProject($site, $baseUrl, $token);
        if ($existing['ok']) {
            return $existing;
        }

        return [
            'ok' => false,
            'url' => null,
            'message' => 'GitLab 專案建立失敗：' . $this->responseMessage($response->json(), $response->body()),
        ];
    }

    /**
     * @return array{ok: bool, url: string|null, message: string, data?: array<string, mixed>}
     */
    private function findExistingProject(Site $site, string $baseUrl, string $token): array
    {
        $namespacePath = trim((string) config('cms.platform.gitlab.namespace_path', ''));
        if ($namespacePath !== '') {
            $projectPath = rawurlencode(trim($namespacePath, '/') . '/' . $site->slug);
            $response = Http::withHeaders(['PRIVATE-TOKEN' => $token])
                ->acceptJson()
                ->get($baseUrl . '/api/v4/projects/' . $projectPath);

            if ($response->successful()) {
                return $this->projectResult($site, $response->json() ?: [], '已找到既有 GitLab 專案。');
            }
        }

        $response = Http::withHeaders(['PRIVATE-TOKEN' => $token])
            ->acceptJson()
            ->get($baseUrl . '/api/v4/projects', [
                'search' => $site->slug,
                'simple' => true,
                'per_page' => 20,
            ]);

        if (!$response->successful()) {
            return ['ok' => false, 'url' => null, 'message' => '找不到既有 GitLab 專案。'];
        }

        $project = collect($response->json() ?: [])
            ->first(fn (array $project): bool => ($project['path'] ?? null) === $site->slug);

        if (!$project) {
            return ['ok' => false, 'url' => null, 'message' => '找不到既有 GitLab 專案。'];
        }

        return $this->projectResult($site, $project, '已找到既有 GitLab 專案。');
    }

    /**
     * @param array<string, mixed> $project
     * @return array{ok: bool, url: string|null, message: string, data: array<string, mixed>}
     */
    private function projectResult(Site $site, array $project, string $message): array
    {
        $url = trim((string) ($project['http_url_to_repo'] ?? $project['ssh_url_to_repo'] ?? ''));
        if ($url === '') {
            return ['ok' => false, 'url' => null, 'message' => 'GitLab 回傳資料缺少 Repository URL。', 'data' => $project];
        }

        $settings = Arr::wrap($site->settings ?? []);
        $deployment = Arr::wrap($settings['deployment'] ?? []);
        $deployment['git_repository_url'] = $url;
        $deployment['gitlab_project_id'] = $project['id'] ?? null;
        $deployment['gitlab_project_path'] = $project['path_with_namespace'] ?? $project['path'] ?? $site->slug;
        $deployment['gitlab_project_created_at'] = $deployment['gitlab_project_created_at'] ?? now()->format('Y-m-d H:i:s');
        $settings['deployment'] = array_filter($deployment, fn ($value) => $value !== null && $value !== '');

        $site->forceFill(['settings' => $settings])->save();

        return ['ok' => true, 'url' => $url, 'message' => $message, 'data' => $project];
    }

    private function responseMessage(mixed $json, string $body): string
    {
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? null;
            if (is_array($message)) {
                return json_encode($message, JSON_UNESCAPED_UNICODE) ?: '未知錯誤';
            }

            if (is_string($message) && trim($message) !== '') {
                return $message;
            }
        }

        return trim($body) !== '' ? trim($body) : '未知錯誤';
    }
}
