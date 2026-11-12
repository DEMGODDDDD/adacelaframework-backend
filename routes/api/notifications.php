<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:customer,admin'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
