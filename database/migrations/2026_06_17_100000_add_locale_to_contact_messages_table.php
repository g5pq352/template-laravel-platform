<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            if (!Schema::hasColumn('contact_messages', 'locale')) {
                $table->string('locale', 40)->default('zh-Hant-TW')->after('type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table): void {
            if (Schema::hasColumn('contact_messages', 'locale')) {
                $table->dropIndex(['locale']);
                $table->dropColumn('locale');
            }
        });
    }
};
