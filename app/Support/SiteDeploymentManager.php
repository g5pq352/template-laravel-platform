<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SiteDeploymentManager
{
    public function ensureAdminAccess(Site $site): Site
    {
        $settings = Arr::wrap($site->settings ?? []);
        $access = Arr::wrap($settings['admin_access'] ?? []);
        $changed = false;

        if (empty($access['url'])) {
            $access['url'] = $this->adminUrl($site);
            $changed = true;
        }

        if (empty($access['username'])) {
            $access['username'] = 'admin';
            $changed = true;
        }

        if (empty($access['password'])) {
            $access['password'] = Crypt::encryptString($this->generatePassword());
            $access['generated_at'] = now()->format('Y-m-d H:i:s');
            $changed = true;
        }

        $settings['admin_access'] = $access;

        $this->mergeDeployment($settings, [
            'initialized_at' => $settings['deployment']['initialized_at'] ?? now()->format('Y-m-d H:i:s'),
            'admin_url' => $settings['deployment']['admin_url'] ?? $settings['admin_access']['url'],
        ]);

        if (!$changed && $settings === Arr::wrap($site->settings ?? [])) {
            return $site;
        }

        $site->forceFill(['settings' => $settings])->save();

        return $site->refresh();
    }

    public function syncAdminAccess(Site $site, array $data): Site
    {
        $settings = Arr::wrap($site->settings ?? []);
        $access = Arr::wrap($settings['admin_access'] ?? []);

        $access['url'] = trim((string) ($data['admin_access_url'] ?? '')) ?: $this->adminUrl($site);
        $access['username'] = trim((string) ($data['admin_access_username'] ?? '')) ?: 'admin';

        $plainPassword = trim((string) ($data['admin_access_password'] ?? ''));
        if ($plainPassword !== '') {
            $access['password'] = Crypt::encryptString($plainPassword);
            $access['updated_at'] = now()->format('Y-m-d H:i:s');
        } elseif (empty($access['password'])) {
            $access['password'] = Crypt::encryptString($this->generatePassword());
            $access['generated_at'] = now()->format('Y-m-d H:i:s');
        }

        $settings['admin_access'] = $access;
        $site->forceFill(['settings' => $settings])->save();

        return $site->refresh();
    }

    /**
     * @return array{url: string|null, username: string|null, password: string|null, generated_at: string|null}
     */
    public function adminAccessForDisplay(Site $site): array
    {
        $access = Arr::wrap($site->settings['admin_access'] ?? []);
        $password = null;

        if (!empty($access['password'])) {
            try {
                $password = Crypt::decryptString((string) $access['password']);
            } catch (\Throwable) {
                $password = null;
            }
        }

        return [
            'url' => $access['url'] ?? $this->adminUrl($site),
            'username' => $access['username'] ?? null,
            'password' => $password,
            'generated_at' => $access['generated_at'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, output: string}
     */
    public function gitPush(Site $site): array
    {
        $repositoryPath = trim((string) ($site->settings['repository_path'] ?? ''));
        $remoteUrl = trim((string) ($site->settings['deployment']['git_repository_url'] ?? ''));

        if ($repositoryPath === '') {
            return ['ok' => false, 'message' => '尚未設定 Git 專案路徑。', 'output' => ''];
        }

        if (!is_dir($repositoryPath)) {
            return ['ok' => false, 'message' => "找不到 Git 專案目錄：{$repositoryPath}", 'output' => ''];
        }

        if ($remoteUrl === '') {
            return ['ok' => false, 'message' => '尚未設定 GitLab Repository URL。', 'output' => ''];
        }

        $remoteName = 'origin';
        $commands = [
            ['git', 'init'],
            ['git', 'config', 'user.email', 'auto@cms-automation.local'],
            ['git', 'config', 'user.name', 'CMS Automation'],
            ['git', 'remote', 'remove', $remoteName],
            ['git', 'remote', 'add', $remoteName, $remoteUrl],
            ['git', 'add', '-A'],
            ['git', 'branch', '-M', 'main'],
            ['git', 'commit', '-m', 'Deployment update', '--allow-empty'],
            ['git', 'push', $remoteName, 'main'],
        ];

        $output = [];
        foreach ($commands as $command) {
            $result = $this->runGitCommand($command, $repositoryPath);
            $output[] = '$ ' . implode(' ', array_map(fn (string $part): string => str_contains($part, ' ') ? '"' . $part . '"' : $part, $command));
            if ($result !== '') {
                $output[] = $result;
            }

            if (!$this->gitCommandCanFail($command) && !$this->lastCommandSuccessful) {
                $message = 'Git Push 失敗，請檢查 Repository URL、權限或本機 Git 設定。';
                $this->rememberGitPushResult($site, false, $message, implode("\n", $output));

                return ['ok' => false, 'message' => $message, 'output' => implode("\n", $output)];
            }
        }

        $message = 'Git Push 已完成。';
        $this->rememberGitPushResult($site, true, $message, implode("\n", $output));

        return ['ok' => true, 'message' => $message, 'output' => implode("\n", $output)];
    }

    private bool $lastCommandSuccessful = true;

    /**
     * @param list<string> $command
     */
    private function runGitCommand(array $command, string $cwd): string
    {
        $process = new Process($command, $cwd);
        $process->setEnv([
            'GIT_TERMINAL_PROMPT' => '0',
            'GCM_INTERACTIVE' => 'never',
        ]);
        $process->setTimeout(600);
        $process->run();

        $this->lastCommandSuccessful = $process->isSuccessful();

        return trim($process->getOutput() . "\n" . $process->getErrorOutput());
    }

    /**
     * @param list<string> $command
     */
    private function gitCommandCanFail(array $command): bool
    {
        return array_slice($command, 0, 3) === ['git', 'remote', 'remove'];
    }

    private function rememberGitPushResult(Site $site, bool $ok, string $message, string $output): void
    {
        $settings = Arr::wrap($site->settings ?? []);
        $this->mergeDeployment($settings, [
            'git_pushed_at' => $ok ? now()->format('Y-m-d H:i:s') : ($settings['deployment']['git_pushed_at'] ?? null),
            'git_push_status' => $ok ? 'success' : 'failed',
            'git_push_message' => $message,
            'git_push_output' => Str::limit($output, 5000, "\n..."),
        ]);

        $site->forceFill(['settings' => $settings])->save();
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $values
     */
    private function mergeDeployment(array &$settings, array $values): void
    {
        $deployment = Arr::wrap($settings['deployment'] ?? []);
        foreach ($values as $key => $value) {
            if ($value !== null && $value !== '') {
                $deployment[$key] = $value;
            }
        }

        $settings['deployment'] = $deployment;
    }

    private function adminUrl(Site $site): string
    {
        $configured = trim((string) ($site->settings['deployment']['admin_url'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $primaryDomain = $site->domains()->where('is_primary', true)->value('domain')
            ?? $site->domains()->value('domain');

        if ($primaryDomain) {
            return 'https://' . trim((string) $primaryDomain, '/') . '/admin/login';
        }

        return url('/admin/login');
    }

    private function generatePassword(): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
