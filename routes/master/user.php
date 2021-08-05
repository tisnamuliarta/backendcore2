<?php

use App\Http\Controllers\Master\MasterUserController;
use App\Http\Controllers\Master\MasterUserDataController;
use Illuminate\Support\Facades\Route;

Route::get('relationship', [MasterUserDataController::class, 'userRelationship']);
//Route::get('whsrelation', [MasterUserDataController::class, 'whsRelationship']);

Route::group(['middleware' => ['permission:Users-edits']], function () {
    Route::get('whs-to', [MasterUserDataController::class, 'getWhsTo']);
    Route::get('whs-relationship', [MasterUserDataController::class, 'whsRelationship']);
    Route::get('get-whs', [MasterUserDataController::class, 'getWarehouse']);
    Route::get('get-whs-index', [MasterUserDataController::class, 'getWarehouseindex']);
    Route::get("item-groups", [MasterUserDataController::class, 'itemGroups']);
    Route::get("user-item-groups", [MasterUserDataController::class, 'userItemGroups']);
    Route::get("m-stages", [MasterUserDataController::class, 'stages']);
    Route::get("user-stages", [MasterUserDataController::class, 'userStages']);
    Route::get("whs", [MasterUserDataController::class, 'getWhs']);
    Route::get("user-whs", [MasterUserDataController::class, 'userWhs']);
    Route::get("users-company", [MasterUserDataController::class, 'userCompany']);
    Route::get('permission', [MasterUserDataController::class, 'userPermission']);
    Route::get('roles', [MasterUserDataController::class, 'userRoles']);

    Route::post("add-item-groups", [MasterUserDataController::class, 'addItemGroups']);
    Route::post("add-stages", [MasterUserDataController::class, 'addStages']);
    Route::post("add-whs", [MasterUserDataController::class, 'addWhs']);
    Route::post('permission', [MasterUserDataController::class, 'storeUserPermission']);
    Route::post("users-add-company", [MasterUserDataController::class, 'addCompany']);
    Route::post("users-add-menu", [MasterUserDataController::class, 'addMenu']);
    Route::post("users-remove-company", [MasterUserDataController::class, 'removeCompany']);
    Route::post("users-remove-menu", [MasterUserDataController::class, 'removeMenu']);
    Route::post("remove-stages", [MasterUserDataController::class, 'removeStages']);
    Route::post("remove-whs", [MasterUserDataController::class, 'removeWhs']);
    Route::post("remove-item-groups", [MasterUserDataController::class, 'removeItemGroups']);
});

Route::apiResource("master", MasterUserController::class);
