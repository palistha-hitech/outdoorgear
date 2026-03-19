<?php

use Illuminate\Support\Facades\Route;

use Modules\Myob\App\Http\Controllers\InventoryController;

// Get products from myob
Route::get('/get-matrix-products', [InventoryController::class, 'getMatrixProducts']);

Route::get('/', function () {
    return view('welcome');
});
