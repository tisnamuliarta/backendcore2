<?php

use App\Http\Controllers\Paper\PaperController;
use Illuminate\Support\Facades\Route;

Route::get('paper/print', [PaperController::class, 'print']);

Route::apiResource('paper', PaperController::class);
