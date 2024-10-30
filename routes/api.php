<?php

use App\Http\Controllers\MerchantLocationController;
use App\Http\Controllers\MerchantMasterController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\JarInputController;
use App\Http\Controllers\ParkingLotController;
use App\Http\Controllers\TestingpdfController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserFormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();

// });

$version = "v1/";
$url = $version;

Route::group([
    'prefix' => $url . 'auth',
    'middleware' => 'api',
], function ($router) {
    $router->post('/register', [AuthController::class, 'register'])->name('register');
    $router->post('/login', [AuthController::class, 'login'])->name('login');
    $router->post('/login-google', [AuthController::class, 'loginGoogle'])->name('login-google');
    $router->post('/verify-google', [AuthController::class, 'verifyGoogle']);
});

/**
 * FORGOT PASSWORD
 */
Route::group([
    'prefix' => $url,
    'middleware' => 'api',
], function ($router) {
    $router->post('forgot-password/generate-otp', [OtpController::class, 'generateOtp']);
    $router->post('forgot-password/validate-otp', [OtpController::class, 'validateOtp']);
    $router->post('/check-otp', [OtpController::class, 'checkOtp']);
    $router->post('/send-otp', [OtpController::class, 'verificationOtp']);
    $router->post('/reset-password', [PasswordController::class, 'resetPassword']);
});

Route::group([
    'prefix' => $url . 'auth',
    'middleware' => 'jwt.verify',
], function ($router) {
    $router->post('/logout', [AuthController::class, 'logout']);
});

/**
 * PROFILE
 */
Route::group([
    'prefix' => $url . 'user',
    'middleware' => 'jwt.verify'
], function ($router) {
    $router->get('/self', [UserController::class, 'index']);
    // $router->put('/update', [ProfileController::class, 'updateUser']);
    $router->put('/change-password', [PasswordController::class, 'changePassword']);
    // $router->put('/update-fcm-token', [FcmController::class, 'updateFcmToken']);
    $router->get('/', [UserController::class, 'showData']);
    $router->get('/{guid}', [UserController::class, 'getData']);
    $router->put('/', [UserController::class, 'updateData']);
    $router->delete('/{guid}', [UserController::class, 'deleteData']);
    $router->post('/', [UserController::class, 'insertData']);
    $router->post('/sync-google', [AuthController::class, 'syncGoogle']);
});

/**
 * FORM
 */
Route::group([
    'prefix' => $url,
    'middleware' => 'jwt.verify'
], function ($router) {
    $router->get('/form', [JarInputController::class, 'getAll']);
    $router->get('/download/{filename}', [JarInputController::class, 'download']);
    $router->get('/form/datatable', [JarInputController::class, 'getAllDataTable']);
    $router->get('/form/{guid}', [JarInputController::class, 'getData']);
    $router->post('/form', [JarInputController::class, 'insertData']);
    $router->post('/updateform', [JarInputController::class, 'updateData']);
    $router->delete('/form/{guid}', [JarInputController::class, 'deleteData']);
});

/**
 * TESTING
 */
Route::group([
    'prefix' => $url,
    'middleware' => 'jwt.verify'
], function ($router) {
    $router->post('/testing', [TestingpdfController::class, 'handleUpload']);
});


