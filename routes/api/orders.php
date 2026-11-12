<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer,admin'])->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::post('orders', [OrderController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('admin/dashboard', [\App\Http\Controllers\Api\AdminDashboardController::class, 'overview']);
    Route::get('admin/reports', [\App\Http\Controllers\Api\AdminDashboardController::class, 'reports']);
    Route::put('orders/{order}', [OrderController::class, 'update']);
    Route::patch('orders/{order}', [OrderController::class, 'update']);
    Route::delete('orders/{order}', [OrderController::class, 'destroy']);
});
