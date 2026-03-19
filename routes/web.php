<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    echo "Hello World";
    echo "Hello World";
});

// Load MYOB module routes
if (file_exists(base_path('Modules/myob/routes/web.php'))) {
    require base_path('Modules/myob/routes/web.php');
}
