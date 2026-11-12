<?php

use App\Http\Controllers\Api\BannerController;
use Illuminate\Support\Facades\Route;

Route::apiResource('banners', BannerController::class)->only(['index', 'show']);
