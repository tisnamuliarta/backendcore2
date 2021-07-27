<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Reservation\ItemController;
use App\Http\Controllers\Reservation\GoodissueController;
use App\Http\Controllers\Reservation\CancelGoodissueController;

Route::post("goodissues/documentDetails", [GoodissueController::class, 'documentDetails']);
Route::post("goodissues/addGoodissues", [GoodissueController::class, 'addGoodissues']);
Route::post("goodissues/chooseBasedoc", [GoodissueController::class, 'chooseBasedoc']);
Route::get("cancelgoodissues/showDetail/{id}/{db_name}", [CancelGoodissueController::class, 'showDetail']);
Route::post("cancelgoodissues/cancelGoodissues", [CancelGoodissueController::class, 'cancelGoodissues']);
Route::post("cancelgoodissues/getDetail", [CancelGoodissueController::class, 'getDetail']);
Route::get("cancelgoodissues/getLine", [CancelGoodissueController::class, 'getLine']);
Route::post("goodissues/cancelGoodissues", [GoodissueController::class, 'cancelGoodissues']);
Route::post('cancelgoodissues/print', [CancelGoodissueController::class, 'printDocument']);

Route::post("items/insert", [ItemController::class, 'insert']);
Route::post("items/update", [ItemController::class, 'update']);
Route::post("item/login", [ItemController::class, 'login']);
Route::post("items/itemgroup", [ItemController::class, 'ItemGroup']);
Route::post("goodissues/login", [GoodissueController::class, 'login']);

Route::apiResource("items", ItemController::class);
Route::apiResource("goodissues", GoodissueController::class);
Route::apiResource("cancelgoodissues", CancelGoodissueController::class);
