<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('content_types')) {
            Schema::create('content_types', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('name', 150);
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['site_id', 'code']);
            });
        }

        if (!Schema::hasTable('contents')) {
            Schema::create('contents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->foreignId('content_type_id')->constrained('content_types')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('contents')->nullOnDelete();
                $table->string('status', 30)->default('draft');
                $table->boolean('is_pinned')->default(false);
                $table->unsignedInteger('sort_order')->default(1);
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['site_id', 'content_type_id', 'status', 'sort_order'], 'contents_list_index');
            });
        }

        if (!Schema::hasTable('content_translations')) {
            Schema::create('content_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
                $table->string('locale', 40);
                $table->string('title')->nullable();
                $table->string('slug')->nullable();
                $table->text('summary')->nullable();
                $table->longText('body')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->json('custom_fields')->nullable();
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['locale', 'slug']);
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('sku', 120)->nullable();
                $table->string('status', 30)->default('draft');
                $table->boolean('is_pinned')->default(false);
                $table->string('product_type', 40)->default('simple');
                $table->decimal('base_price', 12, 2)->default(0);
                $table->decimal('compare_at_price', 12, 2)->nullable();
                $table->decimal('cost_price', 12, 2)->nullable();
                $table->string('currency_code', 10)->default('TWD');
                $table->boolean('is_taxable')->default(false);
                $table->unsignedInteger('sort_order')->default(1);
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['site_id', 'status', 'sort_order']);
            });
        }

        if (!Schema::hasTable('product_translations')) {
            Schema::create('product_translations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('locale', 40);
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->text('summary')->nullable();
                $table->longText('description')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->unsignedBigInteger('view_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['locale', 'slug']);
            });
        }

        if (!Schema::hasTable('taxonomies')) {
            Schema::create('taxonomies', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('code', 80);
                $table->string('name', 150);
                $table->boolean('is_hierarchical')->default(false);
                $table->timestamps();

                $table->unique(['site_id', 'code']);
            });
        }

        if (!Schema::hasTable('taxonomy_terms')) {
            Schema::create('taxonomy_terms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('taxonomy_id')->constrained('taxonomies')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('taxonomy_terms')->nullOnDelete();
                $table->string('locale', 40)->default('zh-Hant-TW');
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->string('seo_title')->nullable();
                $table->text('seo_description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['taxonomy_id', 'locale', 'parent_id', 'sort_order'], 'taxonomy_terms_tree_index');
            });
        }

        if (!Schema::hasTable('content_term')) {
            Schema::create('content_term', function (Blueprint $table): void {
                $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
                $table->foreignId('taxonomy_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(1);

                $table->primary(['content_id', 'taxonomy_term_id']);
            });
        }

        if (!Schema::hasTable('product_category_product')) {
            Schema::create('product_category_product', function (Blueprint $table): void {
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('taxonomy_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(1);

                $table->primary(['product_id', 'taxonomy_term_id']);
            });
        }
    }

    public function down(): void
    {
        // Guarded migration for legacy databases. Keep rollback non-destructive.
    }
};
