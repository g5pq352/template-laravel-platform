<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->string('status', 30)->default('active');
                $table->string('plan_code', 80)->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->string('status', 30)->default('active');
                $table->string('default_locale', 30)->default('zh-Hant-TW');
                $table->string('timezone', 80)->default('Asia/Taipei');
                $table->string('currency_code', 10)->default('TWD');
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('site_domains')) {
            Schema::create('site_domains', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->string('domain', 255)->unique();
                $table->boolean('is_primary')->default(false);
                $table->boolean('force_https')->default(false);
                $table->timestamps();

                $table->index(['site_id', 'is_primary']);
            });
        }

        if (!Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('email', 190)->unique();
                $table->string('password');
                $table->string('status', 30)->default('active');
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 150);
                $table->string('code', 100)->unique();
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admin_site_roles')) {
            Schema::create('admin_site_roles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('admin_user_id')->constrained('admin_users')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->timestamps();

                $table->unique(['admin_user_id', 'site_id']);
            });
        }
    }

    public function down(): void
    {
        // This migration is intentionally guarded for legacy databases that may
        // already have these base tables. Avoid destructive rollback here.
    }
};
