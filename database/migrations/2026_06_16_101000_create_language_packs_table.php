<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('language_packs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['site_id', 'key']);
            $table->index(['site_id', 'sort_order']);
        });

        Schema::create('language_pack_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('language_pack_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 40);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['language_pack_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_pack_translations');
        Schema::dropIfExists('language_packs');
    }
};
