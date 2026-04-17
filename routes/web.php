<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\CompanyController;
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
Route::get("/getAccessCode-1", [AuthController::class,'getAccessCode']); 
Route::get("/get-access-code", [AuthController::class,'getAccessCode']);
Route::get("/handleAccessCode", [AuthController::class,'handleAccessCode']);
Route::get("/authorize", [AuthController::class,'getAccessToken']);
Route::get("/refreshToken", [AuthController::class,'refreshToken']);
Route::get("/listCompanyFiles", [CompanyController::class,'listCompanyFiles']);