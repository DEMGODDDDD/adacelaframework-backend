<?php

use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
    Route::apiResource('wishlist', WishlistController::class);
});
