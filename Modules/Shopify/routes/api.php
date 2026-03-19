<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\CollectionController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\DeletedProductController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\ImagesController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\LocationController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\PriceController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\ProductController;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\SohController;
use Modules\Shopify\App\Http\Controllers\ReadShopify\OrderController;
use Modules\Shopify\App\Http\Controllers\ReadShopify\ShopifyController;
use Modules\Shopify\App\Http\Controllers\ReadShopify\ShopifyProductsController;
use Modules\Shopify\App\Http\Controllers\ReadShopify\ShopifyWebHookController;
use Modules\Shopify\App\Http\Controllers\ViewController\OrderViewController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\GiftCardController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\OrderFulfilledController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\ShopifyCustomerController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourceProductController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourceCollectionController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourceImageController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourcePriceController;
use Modules\Shopify\App\Http\Controllers\WriteShopify\SourceSohController;
use Modules\Shopify\App\Http\Controllers\WriteShopifyV2\SourceImageController as WriteShopifyV2SourceImageController;
use Modules\Shopify\App\Http\Controllers\WriteShopifyV2\SourceProductController as WriteShopifyV2SourceProductController;
use Modules\Shopify\App\Http\Controllers\WriteShopifyV2\SourceSohController as WriteShopifyV2SourceSohController;
use Modules\Shopify\App\Http\Controllers\WriteShopifyV2\SourceVariationController;
use Modules\Shopify\App\Http\Controllers\ReadShopify\EmailController;



// Route::get('refunds', [ShopifyController::class, 'getRefund']);
// Route::get('getorders', [OrderController::class, 'getOrders']);
// Route::get('getrefunds', [OrderController::class, 'getRefunds']);
// Route::get('getrefunds-v2', [OrderController::class, 'getRefundsV2']);

// # update the unfulfilled orders to fulfilled
// Route::get('update-order', [OrderFulfilledController::class, 'index']);

// # routes for shopify  push
Route::get('push-products', [SourceProductController::class, 'index']); //done
// Route::get('push-collections', [SourceCollectionController::class, 'index']);
// Route::get('push-images', [SourceImageController::class, 'index']);
// Route::get('add-variants-media', [SourceImageController::class, 'addvariantsToMedia']);
Route::get('push-product-soh', [SourceSohController::class, 'index']);
// Route::get('push-price', [SourcePriceController::class, 'index']);

// # added by suraj
// Route::get('push-variants', [SourceProductController::class, 'pushVariants']);

Route::get('v1/push-variants', [SourceProductController::class, 'pushVariantsV1']);
// // Route::get('update-archived-variants', [SourceProductController::class, 'updateArchivedVariants']);
// Route::get('/send/email', [EmailController::class, 'sendEmail']);
// Route::get('/send-alert/failed-soh', [EmailController::class, 'getFailedSOH']);

// # move products details from erplay to module

// Route::get('get-products', [ProductController::class, 'getProducts']);

// Route::get('get-category', [CollectionController::class, 'getCategory']);

// Route::get('get-locations', [LocationController::class, 'getLocation']);

// Route::get('get-images', [ImagesController::class, 'index']);

Route::get('get-soh', [SohController::class, 'index']);

// Route::get('get-price', [PriceController::class, 'index']);
// #Web Hooks

// Route::post('shopify/webhooks/orders', [ShopifyWebHookController::class, 'handleOrderwebHooks']);

// Route::get('get-tags', [ProductController::class, 'getTags']);

// Route::get('get-deleted-products-from-erply', [DeletedProductController::class, 'index']);
// // Route::get('get-deleted-from-martix-to-source', [DeletedProductController::class, 'deletedfrommatrix']);


// #Shopify Products

// Route::get('getShopifyProducts', [ShopifyProductsController::class, 'getProducts']);
// Route::get('getVariantsProducts', [ShopifyProductsController::class, 'getVariantsProducts']);

// Route::get('delete-laravel-logs', [OrderViewController::class, 'deleteLaravelLogs']);
// #Route::post('webhook-product', [ShopifyProductsController::class, 'handleProductwebHooks']);
// Route::any('/webhooks-customers', function (Request $request) {
//     // Log raw dataa
//     Log::info('Raw Webhook Data:', ['payload' => $request->getContent()]);

//     // Log parsed data
//     Log::info('Parsed Webhook Data:', $request->all());

//     // Return a 200 OK response
//     return response()->json(['status' => 'success'], 200);
// });


// Route::post('webhook-product', [ShopifyProductsController::class, 'handleProductwebHooks']);

// #get customers from the Shopify
// Route::get('/get-shopify-customers', [ShopifyCustomerController::class, 'index']);

// #push the Gift cards to the Shopify
// Route::get('/push-gift-card', [GiftCardController::class, 'index']);

// Route::get('/delete-product-from-shopify', [SourceProductController::class, 'deletefromShopify']);
// Route::get('/delete-product-variants-from-shopify', [SourceProductController::class, 'deleteVariantFromShopify']); // Just variants whose status = ARCHIVED

//Shopify Api Package wala
// Route::group(['prefix' => 'shopify-write'], function () {
    // Route::get('/product', [WriteShopifyV2SourceProductController::class, 'index']);
//     Route::get('/variation', [SourceVariationController::class, 'index']);
//     Route::get('/image', [WriteShopifyV2SourceImageController::class, 'index']);
//     Route::get('/variation-image-append', [WriteShopifyV2SourceImageController::class, 'addvariantsToMedia']);
//     Route::get('/soh', [WriteShopifyV2SourceSohController::class, 'index']);
// });