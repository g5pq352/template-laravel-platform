<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table): void {
            if (!Schema::hasColumn('contents', 'view_count')) {
                $table->unsignedBigInteger('view_count')->default(0)->after('sort_order');
            }
        });

        Schema::table('products', function (Blueprint $table): void {
            if (!Schema::hasColumn('products', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('status');
            }

            if (!Schema::hasColumn('products', 'view_count')) {
                $table->unsignedBigInteger('view_count')->default(0)->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'view_count')) {
                $table->dropColumn('view_count');
            }

            if (Schema::hasColumn('products', 'is_pinned')) {
                $table->dropColumn('is_pinned');
            }
        });

        Schema::table('contents', function (Blueprint $table): void {
            if (Schema::hasColumn('contents', 'view_count')) {
                $table->dropColumn('view_count');
            }
        });
    }
};
