<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['content_translations', 'product_translations'] as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'view_count')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                    $after = $tableName === 'content_translations' ? 'custom_fields' : 'seo_description';
                    $table->unsignedBigInteger('view_count')->default(0)->after($after);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['content_translations', 'product_translations'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'view_count')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('view_count');
                });
            }
        }
    }
};
