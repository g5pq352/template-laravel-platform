<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_sort_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('resource', 80);
            $table->unsignedBigInteger('resource_id');
            $table->foreignId('taxonomy_term_id')->nullable()->constrained('taxonomy_terms')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->unique(['site_id', 'resource', 'taxonomy_term_id', 'resource_id'], 'resource_sort_scope_unique');
            $table->index(['site_id', 'resource', 'taxonomy_term_id', 'sort_order'], 'resource_sort_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_sort_orders');
    }
};
