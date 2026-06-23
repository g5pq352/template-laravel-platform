<?php

namespace App\Support;

use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SiteDatabaseProvisioner
{
    /**
     * @return array{ok: bool, created: bool, imported: bool, message: string}
     */
    public function provision(Site $site): array
    {
        if (app()->runningUnitTests()) {
            $message = '測試環境略過資料庫實體建立。';
            $this->rememberResult($site, trim((string) ($site->settings['database']['database'] ?? $site->slug)), false, 'skipped', $message);

            return ['ok' => true, 'created' => false, 'imported' => false, 'message' => $message];
        }

        if (!config('cms.platform.site_database_auto_provision', true)) {
            $message = '資料庫自動建立未啟用。';
            $this->rememberResult($site, trim((string) ($site->settings['database']['database'] ?? $site->slug)), false, 'skipped', $message);

            return ['ok' => true, 'created' => false, 'imported' => false, 'message' => $message];
        }

        $database = trim((string) ($site->settings['database']['database'] ?? $site->slug));
        if ($database === '') {
            $message = '站台資料庫名稱為空。';
            $this->rememberResult($site, '', false, 'failed', $message);

            return ['ok' => false, 'created' => false, 'imported' => false, 'message' => $message];
        }

        DB::statement(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->quoteIdentifier($database)
        ));

        $imported = $this->importTemplateIfConfigured($database);
        $this->rememberResult($site, $database, $imported, 'success', $imported ? '站台資料庫已建立並匯入範本。' : '站台資料庫已建立。');

        return [
            'ok' => true,
            'created' => true,
            'imported' => $imported,
            'message' => $imported ? '站台資料庫已建立並匯入範本。' : '站台資料庫已建立。',
        ];
    }

    private function importTemplateIfConfigured(string $database): bool
    {
        $path = trim((string) config('cms.platform.site_database_template_path', ''));
        if ($path === '' || !File::exists($path)) {
            return false;
        }

        $sql = File::get($path);
        if (trim($sql) === '') {
            return false;
        }

        DB::statement('USE ' . $this->quoteIdentifier($database));
        foreach ($this->splitSqlStatements($sql) as $statement) {
            DB::unprepared($statement);
        }
        DB::reconnect();

        return true;
    }

    /**
     * @return list<string>
     */
    private function splitSqlStatements(string $sql): array
    {
        return collect(preg_split('/;\s*(?:\r\n|\r|\n|$)/', $sql) ?: [])
            ->map(fn (string $statement): string => trim($statement))
            ->filter(fn (string $statement): bool => $statement !== '' && !str_starts_with($statement, '--'))
            ->values()
            ->all();
    }

    private function rememberResult(Site $site, string $database, bool $imported, string $status, string $message): void
    {
        $settings = Arr::wrap($site->settings ?? []);
        $deployment = Arr::wrap($settings['deployment'] ?? []);
        $deployment['database_name'] = $database;
        $deployment['database_status'] = $status;
        $deployment['database_message'] = $message;

        if ($status === 'success') {
            $deployment['database_created_at'] = $deployment['database_created_at'] ?? now()->format('Y-m-d H:i:s');
        } elseif ($status === 'failed') {
            $deployment['database_failed_at'] = now()->format('Y-m-d H:i:s');
        }

        if ($imported) {
            $deployment['database_template_imported_at'] = now()->format('Y-m-d H:i:s');
        }

        $settings['deployment'] = $deployment;
        $site->forceFill(['settings' => $settings])->save();
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
