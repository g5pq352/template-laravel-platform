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

        $sites = DB::table('sites')->select('id')->get();
        $now = now();

        foreach ($sites as $site) {
            DB::table('cms_menus')->updateOrInsert(
                [
                    'site_id' => $site->id,
                    'location' => 'backend',
                    'parent_id' => null,
                    'module_key' => 'sites',
                ],
                [
                    'title' => '多站管理',
                    'type' => 'route',
                    'route_name' => 'admin.sites.index',
                    'url' => null,
                    'icon' => 'bx bx-buildings',
                    'target' => '_self',
                    'is_active' => true,
                    'sort_order' => 99,
                    'settings' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        DB::table('cms_menus')
            ->where('location', 'backend')
            ->where('module_key', 'sites')
            ->delete();
    }
};
