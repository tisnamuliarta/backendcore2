<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Master\MasterAirportController;
use App\Http\Controllers\Master\MasterCompanyController;
use App\Http\Controllers\Master\MasterDataController;
use App\Http\Controllers\Master\MasterEmployeeController;
use App\Http\Controllers\Master\MasterPaperController;
use App\Http\Controllers\Master\MasterPermissionController;
use App\Http\Controllers\Master\MasterRolesController;
use App\Http\Controllers\Master\MasterWhsController;
use Illuminate\Support\Facades\Route;

Route::get('apps', [AppController::class, 'frontData']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('download-manual', [HomeController::class, 'downloadManual']);

Route::get('verification/{url}', [\App\Http\Controllers\VerificationController::class, 'verification']);

Route::post('callback', [\App\Http\Controllers\CherryApprovalController::class, 'callback']);
Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
Route::post('/generate-str', function () {
   return response()->json(\Illuminate\Support\Str::random(40));
});

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::group(['prefix' => 'auth'], function () {
        Route::get('/roles', [AuthController::class, 'roles']);
        Route::get('/permissions', [AuthController::class, 'permissions']);
        Route::get('/me', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    Route::get('home-data', [HomeController::class, 'homeData']);
    Route::get('menus', [HomeController::class, 'menus']);

    Route::get('item-master-data', [MasterDataController::class, 'getItemMasterData']);
    Route::get('latest-req-item', [MasterDataController::class, 'getLatestRequest']);
    Route::get('list-latest-req', [MasterDataController::class, 'getListRequest']);

    Route::get('attachment', [AttachmentController::class, 'index']);
    Route::post('attachment', [AttachmentController::class, 'store']);
    Route::delete('attachment', [AttachmentController::class, 'destroy']);

    Route::prefix('reservation')
        ->group(__DIR__ . '/transaction/reservation.php');

    // e-reservation inventory route
    Route::group([], __DIR__ . '/transaction/inventory.php');

    // Paper Route
    Route::group([], __DIR__ . '/transaction/paper.php');

    Route::group(['prefix' => 'export'], function() {
        Route::get('paper-rapid', [\App\Http\Controllers\Export\ExportDataController::class, 'exportRapid']);
    });

    Route::group(['prefix' => 'master'], function () {
        Route::get('employees', [MasterEmployeeController::class, 'index']);
        Route::get('employee-leave/{nik}', [MasterEmployeeController::class, 'leave']);
        Route::get('employee-by-name', [MasterEmployeeController::class, 'employeeByName']);
        Route::get('division', [MasterCompanyController::class, 'division']);
        Route::get('whs', [MasterWhsController::class, 'index']);
        Route::get('item-group-code', [MasterDataController::class, 'getItemGroupCode']);

        Route::prefix('users')
            ->group(__DIR__ . '/master/user.php');

        Route::get('permission-role', [MasterRolesController::class, 'permissionRole']);
        Route::post('permission-role', [MasterRolesController::class, 'storePermissionRole']);

        Route::apiResource('airport', MasterAirportController::class);
        Route::apiResource('papers', MasterPaperController::class);
        Route::apiResource('apps', AppController::class);
        Route::apiResource('permissions', MasterPermissionController::class);
        Route::apiResource('roles', MasterRolesController::class);
    });
});
