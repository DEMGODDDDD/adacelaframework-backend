<?php

use App\Http\Controllers\Api\InventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::apiResource('inventories', InventoryController::class)
        ->only(['index', 'store', 'show', 'update']);
    Route::patch('inventories/{inventory}/adjust', [InventoryController::class, 'adjust']);
});
