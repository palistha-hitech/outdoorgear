<?php

use Illuminate\Support\Facades\Route;
use Modules\Shopify\App\Http\Controllers\ReadShopify\ShopifyWebHookController;
use Modules\Shopify\App\Http\Controllers\ShopifyController;
use Modules\Shopify\App\Http\Controllers\ViewController\OrderViewController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// require __DIR__ . '/myob.php';
Route::group([], function () {
    Route::resource('shopify', ShopifyController::class)->names('shopify');
    Route::get('order-details', [OrderViewController::class, 'index'])->name('order-details');
});

Route::post('shopify/webhooks/orders', [ShopifyWebHookController::class, 'handleOrderwebHooks']);
