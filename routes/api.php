<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Master\MasterDataController;
use App\Http\Controllers\Master\MasterPermissionController;
use App\Http\Controllers\Master\MasterRolesController;
use Illuminate\Support\Facades\Route;

Route::get('apps', [\App\Http\Controllers\AppController::class, 'frontData']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::post('callback', [\App\Http\Controllers\CherryApprovalController::class, 'callback']);

Route::group(['middleware' => ['api', 'auth:api']], function () {
    Route::group(['prefix' => 'auth'], function () {
        Route::get('/me', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    Route::get('/table', [HomeController::class, 'table']);
    Route::get('home-data', [HomeController::class, 'homeData']);
    Route::get('menus', [HomeController::class, 'menus']);

    Route::get('item-master-data', [MasterDataController::class, 'getItemMasterData']);
    Route::get('latest-req-item', [MasterDataController::class, 'getLatestRequest']);
    Route::get('list-latest-req', [MasterDataController::class, 'getListRequest']);

    Route::prefix('reservation')
        ->group(__DIR__ . '/transaction/reservation.php');

    Route::group([], __DIR__ . '/transaction/inventory.php');

    Route::group(['prefix' => 'master'], function () {
        Route::get('division', [\App\Http\Controllers\Master\MasterCompanyController::class, 'division']);
        Route::prefix('users')
            ->group(__DIR__ . '/master/user.php');

        Route::get('permission-role', [MasterRolesController::class, 'permissionRole']);
        Route::post('permission-role', [MasterRolesController::class, 'storePermissionRole']);

        Route::apiResource('apps', \App\Http\Controllers\AppController::class);
        Route::apiResource('company', \App\Http\Controllers\Master\MasterCompanyController::class);
        Route::apiResource('permissions', MasterPermissionController::class);
        Route::apiResource('roles', MasterRolesController::class);
    });
});
