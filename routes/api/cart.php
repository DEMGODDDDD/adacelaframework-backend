<?php

use App\Http\Controllers\Api\CartController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::get('cart', [CartController::class, 'show']);
    Route::delete('cart', [CartController::class, 'clear']);
});
