<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms_menus') || !Schema::hasTable('sites')) {
            return;
        }

        $mainSiteSlug = config('cms.platform.main_site_slug', 'main-site');

        DB::table('cms_menus')
            ->where('location', 'backend')
            ->where('module_key', 'sites')
            ->whereIn('site_id', function ($query) use ($mainSiteSlug): void {
                $query->select('id')
                    ->from('sites')
                    ->where('slug', '!=', $mainSiteSlug);
            })
            ->delete();
    }

    public function down(): void
    {
        // Non-destructive: child sites should not regain platform management menus.
    }
};
