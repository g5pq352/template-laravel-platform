<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CmsMenuController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InfoModuleController;
use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Admin\TaxonomyTermController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest.admin')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    });

    Route::middleware('admin.session')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/sites/switch', [SiteController::class, 'switch'])->name('sites.switch');
        Route::get('/settings', fn () => redirect()->route('admin.info.edit', 'keywordsInfo'))->name('settings.index');
        Route::get('/info/{module}', [InfoModuleController::class, 'edit'])->name('info.edit');
        Route::put('/info/{module}', [InfoModuleController::class, 'update'])->name('info.update');

        Route::get('/menus', [CmsMenuController::class, 'index'])->name('menus.index');
        Route::get('/menus/create', [CmsMenuController::class, 'create'])->name('menus.create');
        Route::post('/menus', [CmsMenuController::class, 'store'])->name('menus.store');
        Route::post('/menus/bulk-action', [CmsMenuController::class, 'bulkAction'])->name('menus.bulk-action');
        Route::get('/menus/{menu}/edit', [CmsMenuController::class, 'edit'])->name('menus.edit');
        Route::put('/menus/{menu}', [CmsMenuController::class, 'update'])->name('menus.update');
        Route::delete('/menus/{menu}', [CmsMenuController::class, 'destroy'])->name('menus.destroy');
        Route::post('/menus/{id}/restore', [CmsMenuController::class, 'restore'])->name('menus.restore');
        Route::delete('/menus/{id}/force-delete', [CmsMenuController::class, 'forceDelete'])->name('menus.force-delete');
        Route::post('/menus/{menu}/sort', [CmsMenuController::class, 'sort'])->name('menus.sort');
        Route::post('/menus/{menu}/toggle-status', [CmsMenuController::class, 'toggleStatus'])->name('menus.toggle-status');

        Route::get('/contents', [ContentController::class, 'index'])->name('contents.index');
        Route::get('/contents/create', [ContentController::class, 'create'])->name('contents.create');
        Route::post('/contents', [ContentController::class, 'store'])->name('contents.store');
        Route::get('/contents/{content}/edit', [ContentController::class, 'edit'])->name('contents.edit');
        Route::put('/contents/{content}', [ContentController::class, 'update'])->name('contents.update');
        Route::delete('/contents/{content}', [ContentController::class, 'destroy'])->name('contents.destroy');

        foreach (['news', 'products', 'contact'] as $resource) {
            Route::get("/{$resource}", [ResourceController::class, 'index'])->defaults('resource', $resource)->name("{$resource}.index");
            Route::get("/{$resource}/create", [ResourceController::class, 'create'])->defaults('resource', $resource)->name("{$resource}.create");
            Route::post("/{$resource}", [ResourceController::class, 'store'])->defaults('resource', $resource)->name("{$resource}.store");
            Route::post("/{$resource}/bulk-action", [ResourceController::class, 'bulkAction'])->defaults('resource', $resource)->name("{$resource}.bulk-action");
            Route::get("/{$resource}/{id}/edit", [ResourceController::class, 'edit'])->defaults('resource', $resource)->name("{$resource}.edit");
            Route::put("/{$resource}/{id}", [ResourceController::class, 'update'])->defaults('resource', $resource)->name("{$resource}.update");
            Route::delete("/{$resource}/{id}", [ResourceController::class, 'destroy'])->defaults('resource', $resource)->name("{$resource}.destroy");
            Route::post("/{$resource}/{id}/restore", [ResourceController::class, 'restore'])->defaults('resource', $resource)->name("{$resource}.restore");
            Route::delete("/{$resource}/{id}/force-delete", [ResourceController::class, 'forceDelete'])->defaults('resource', $resource)->name("{$resource}.force-delete");
            Route::post("/{$resource}/{id}/toggle-status", [ResourceController::class, 'toggleStatus'])->defaults('resource', $resource)->name("{$resource}.toggle-status");
            Route::post("/{$resource}/{id}/toggle-pin", [ResourceController::class, 'togglePin'])->defaults('resource', $resource)->name("{$resource}.toggle-pin");
            Route::post("/{$resource}/{id}/sort", [ResourceController::class, 'sort'])->defaults('resource', $resource)->name("{$resource}.sort");
        }

        Route::get('/taxonomies/{taxonomy}', [TaxonomyTermController::class, 'index'])->name('taxonomies.index');
        Route::get('/taxonomies/{taxonomy}/create', [TaxonomyTermController::class, 'create'])->name('taxonomies.create');
        Route::post('/taxonomies/{taxonomy}', [TaxonomyTermController::class, 'store'])->name('taxonomies.store');
        Route::get('/taxonomies/{taxonomy}/{term}/edit', [TaxonomyTermController::class, 'edit'])->name('taxonomies.edit');
        Route::put('/taxonomies/{taxonomy}/{term}', [TaxonomyTermController::class, 'update'])->name('taxonomies.update');
        Route::delete('/taxonomies/{taxonomy}/{term}', [TaxonomyTermController::class, 'destroy'])->name('taxonomies.destroy');
        Route::post('/taxonomies/{taxonomy}/bulk-action', [TaxonomyTermController::class, 'bulkAction'])->name('taxonomies.bulk-action');
        Route::post('/taxonomies/{taxonomy}/{id}/restore', [TaxonomyTermController::class, 'restore'])->name('taxonomies.restore');
        Route::delete('/taxonomies/{taxonomy}/{id}/force-delete', [TaxonomyTermController::class, 'forceDelete'])->name('taxonomies.force-delete');
        Route::post('/taxonomies/{taxonomy}/{term}/sort', [TaxonomyTermController::class, 'sort'])->name('taxonomies.sort');
        Route::post('/taxonomies/{taxonomy}/{term}/toggle-status', [TaxonomyTermController::class, 'toggleStatus'])->name('taxonomies.toggle-status');
    });
});
