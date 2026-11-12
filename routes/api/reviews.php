<?php

use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer,admin'])->group(function () {
    Route::get('reviews', [ReviewController::class, 'index']);
    Route::get('reviews/{review}', [ReviewController::class, 'show']);
    Route::put('reviews/{review}', [ReviewController::class, 'update']);
    Route::patch('reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('admin/reviews', [ReviewController::class, 'adminIndex']);
});

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::post('reviews', [ReviewController::class, 'store']);
});
