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

        $now = now();
        $sites = DB::table('sites')->select('id')->get();

        foreach ($sites as $site) {
            $exists = DB::table('cms_menus')
                ->where('site_id', $site->id)
                ->where('location', 'backend')
                ->where('module_key', 'media-library')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cms_menus')->insert([
                'site_id' => $site->id,
                'parent_id' => null,
                'location' => 'backend',
                'title' => '圖片庫',
                'type' => 'route',
                'url' => null,
                'route_name' => 'admin.media-library.index',
                'module_key' => 'media-library',
                'icon' => 'bx bx-images',
                'target' => '_self',
                'is_active' => true,
                'sort_order' => 2,
                'settings' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        DB::table('cms_menus')
            ->where('location', 'backend')
            ->where('module_key', 'media-library')
            ->where('route_name', 'admin.media-library.index')
            ->delete();
    }
};
