<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cms_menus')) {
            return;
        }

        Schema::create('cms_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cms_menus')->nullOnDelete();
            $table->string('location', 50)->default('backend');
            $table->string('title', 150);
            $table->string('type', 30)->default('custom');
            $table->string('url', 500)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('module_key', 100)->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('target', 20)->default('_self');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'location', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
