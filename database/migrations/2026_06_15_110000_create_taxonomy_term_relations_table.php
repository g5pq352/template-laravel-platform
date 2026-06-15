<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('taxonomy_term_relations')) {
            return;
        }

        Schema::create('taxonomy_term_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
            $table->foreignId('related_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
            $table->string('field', 80)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['source_term_id', 'related_term_id', 'field'], 'taxonomy_term_relations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_term_relations');
    }
};
