<?php

use App\Http\Controllers\Api\OrderItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer,admin'])->group(function () {
    Route::apiResource('order-items', OrderItemController::class)
        ->only(['index', 'show']);
});
