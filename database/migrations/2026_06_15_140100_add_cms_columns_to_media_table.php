<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->foreignId('site_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('folder_id')->nullable()->after('site_id')->constrained('media_folders')->nullOnDelete();
            $table->string('alt_text')->nullable()->after('file_name');
            $table->softDeletes();

            $table->index(['site_id', 'folder_id', 'collection_name']);
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['site_id', 'folder_id', 'collection_name']);
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['alt_text', 'deleted_at']);
        });
    }
};
