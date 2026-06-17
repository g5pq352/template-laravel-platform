<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_menus', function (Blueprint $table): void {
            if (!Schema::hasColumn('cms_menus', 'locale')) {
                $table->string('locale', 40)->default('zh-Hant-TW')->after('location')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cms_menus', function (Blueprint $table): void {
            if (Schema::hasColumn('cms_menus', 'locale')) {
                $table->dropIndex(['locale']);
                $table->dropColumn('locale');
            }
        });
    }
};
