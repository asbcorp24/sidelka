<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/bootstrap', [MarketplaceController::class, 'bootstrap']);
Route::get('/caregivers', [MarketplaceController::class, 'caregivers']);
Route::get('/news', [MarketplaceController::class, 'news']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [MarketplaceController::class, 'me']);
    Route::get('/orders', [MarketplaceController::class, 'orders']);
    Route::post('/orders', [MarketplaceController::class, 'storeOrder']);
    Route::post('/caregiver/profile', [MarketplaceController::class, 'updateCaregiverProfile']);
});
