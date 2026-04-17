<?php

use Illuminate\Support\Facades\Route;
use Modules\Myob\App\Http\Controllers\InventoryController;
use Modules\Myob\App\Http\Controllers\DashboardController;

// Authentication routes
Route::get('/login', [DashboardController::class, 'showLoginForm'])->name('login');
Route::post('/login', [DashboardController::class, 'login']);
Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
//Invetory
Route::get("/getInventoryItems", [InventoryController::class,'getInventoryItems']);
// Protected dashboard routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/sync-status', [DashboardController::class, 'getSyncStatus']);
    Route::get('/dashboard/sync-logs', [DashboardController::class, 'getSyncLogs']);
    Route::post('/dashboard/sync-myob-to-local', [DashboardController::class, 'syncMyobToLocal']);
    Route::post('/dashboard/sync-local-to-shopify', [DashboardController::class, 'syncLocalToShopify']);
    Route::post('/dashboard/sync-shopify-to-local', [DashboardController::class, 'syncShopifyToLocal']);
    Route::post('/dashboard/sync-local-to-myob', [DashboardController::class, 'syncLocalToMyob']);
});

// API routes
Route::get('/get-matrix-products', [InventoryController::class, 'getMatrixProducts']);
Route::post('/sync-to-myob', [InventoryController::class, 'syncToMyob']);


