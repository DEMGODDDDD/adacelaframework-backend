<?php

use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
});
