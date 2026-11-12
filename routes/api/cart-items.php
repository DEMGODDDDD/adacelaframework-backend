<?php

use App\Http\Controllers\Api\CartItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::post('cart', [CartItemController::class, 'store']);
    Route::get('cart/{cartItem}', [CartItemController::class, 'show']);
    Route::put('cart/{cartItem}', [CartItemController::class, 'update']);
    Route::patch('cart/{cartItem}', [CartItemController::class, 'update']);
    Route::delete('cart/{cartItem}', [CartItemController::class, 'destroy']);
});
