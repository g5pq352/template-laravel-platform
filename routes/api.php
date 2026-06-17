<?php

use App\Http\Controllers\Api\PublicSiteController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.site')->group(function (): void {
    Route::get('/site', [PublicSiteController::class, 'site'])->name('api.site');
    Route::get('/languages', [PublicSiteController::class, 'languages'])->name('api.languages');
    Route::get('/language-packs', [PublicSiteController::class, 'languagePacks'])->name('api.language-packs');
    Route::get('/home-display', [PublicSiteController::class, 'homeDisplay'])->name('api.home-display');
    Route::get('/menus', [PublicSiteController::class, 'menus'])->name('api.menus');
    Route::get('/taxonomies/{code}', [PublicSiteController::class, 'taxonomy'])->name('api.taxonomies.show');
    Route::get('/contents/{type}', [PublicSiteController::class, 'contents'])->name('api.contents.index');
    Route::post('/contents/{type}/{slug}/view', [PublicSiteController::class, 'trackContentView'])->name('api.contents.view');
    Route::get('/contents/{type}/{slug}', [PublicSiteController::class, 'content'])->name('api.contents.show');
    Route::get('/products', [PublicSiteController::class, 'products'])->name('api.products.index');
    Route::get('/products/{slug}', [PublicSiteController::class, 'product'])->name('api.products.show');
});
