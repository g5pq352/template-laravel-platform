<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyOrder([
            'dashboard' => 1,
            'media-library' => 2,
            'home' => 3,
            'news' => 4,
            'products' => 5,
            'contact' => 6,
            'settings' => 7,
            'permissions' => 8,
            'menus' => 9,
        ]);
    }

    public function down(): void
    {
        $this->applyOrder([
            'dashboard' => 1,
            'home' => 2,
            'news' => 3,
            'products' => 4,
            'contact' => 5,
            'settings' => 6,
            'permissions' => 7,
            'menus' => 8,
            'media-library' => 80,
        ]);
    }

    /**
     * @param array<string, int> $orders
     */
    private function applyOrder(array $orders): void
    {
        if (!Schema::hasTable('cms_menus')) {
            return;
        }

        foreach ($orders as $moduleKey => $sortOrder) {
            DB::table('cms_menus')
                ->where('location', 'backend')
                ->whereNull('parent_id')
                ->where('module_key', $moduleKey)
                ->update([
                    'sort_order' => $sortOrder,
                    'updated_at' => now(),
                ]);
        }
    }
};
