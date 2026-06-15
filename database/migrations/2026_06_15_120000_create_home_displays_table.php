<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_displays')) {
            return;
        }

        Schema::create('home_displays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->string('module', 80)->default('news')->index();
            $table->string('locale', 20)->default('zh-Hant-TW')->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['site_id', 'module', 'locale', 'content_id'], 'home_displays_unique_content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_displays');
    }
};
