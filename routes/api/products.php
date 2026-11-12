<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::apiResource('products', ProductController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('products', ProductController::class)->except(['index', 'show']);
});
