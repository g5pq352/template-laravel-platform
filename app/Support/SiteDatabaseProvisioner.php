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
            return ['ok' => true, 'created' => false, 'imported' => false, 'message' => '測試環境略過資料庫實體建立。'];
        }

        if (!config('cms.platform.site_database_auto_provision', true)) {
            return ['ok' => true, 'created' => false, 'imported' => false, 'message' => '資料庫自動建立未啟用。'];
        }

        $database = trim((string) ($site->settings['database']['database'] ?? $site->slug));
        if ($database === '') {
            return ['ok' => false, 'created' => false, 'imported' => false, 'message' => '站台資料庫名稱為空。'];
        }

        DB::statement(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $this->quoteIdentifier($database)
        ));

        $imported = $this->importTemplateIfConfigured($database);
        $this->rememberResult($site, $database, $imported);

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

    private function rememberResult(Site $site, string $database, bool $imported): void
    {
        $settings = Arr::wrap($site->settings ?? []);
        $deployment = Arr::wrap($settings['deployment'] ?? []);
        $deployment['database_name'] = $database;
        $deployment['database_created_at'] = $deployment['database_created_at'] ?? now()->format('Y-m-d H:i:s');

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
