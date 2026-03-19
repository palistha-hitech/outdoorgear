<?php

use App\Http\Controllers\products\SyncProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Shopify\App\Http\Controllers\Middleware\Erply\ProductController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

#get products from Erply
Route::post('/erply/getProducts',[SyncProductController::class,  'syncErplyToShopify']);