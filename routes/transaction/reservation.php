<?php

use App\Http\Controllers\Reservation\TransactionApprovalController;
use App\Http\Controllers\Reservation\TransactionReservationController;
use Illuminate\Support\Facades\Route;

Route::get('print', [TransactionReservationController::class, 'printDocument']);
Route::get('max-doc-resv', [TransactionReservationController::class, 'maxDocResv']);
Route::get('fetch-docnum', [TransactionReservationController::class, 'fetchDocNum']);
Route::get('approval-list', [TransactionApprovalController::class, 'index']);
Route::get('approval-stages', [TransactionApprovalController::class, 'approvalStages']);

Route::delete('delete-all/{id}', [TransactionReservationController::class, 'deleteAll']);

Route::post('submit-approval', [TransactionReservationController::class, 'submitApproval']);
Route::post('action', [TransactionApprovalController::class, 'action']);

Route::apiResource('master', TransactionReservationController::class);
