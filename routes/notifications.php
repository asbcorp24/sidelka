<?php

use App\Http\Controllers\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [NotificationCenterController::class, 'index'])
    ->name('notifications.index');
Route::get('/notifications/{notification}/open', [NotificationCenterController::class, 'openNotification'])
    ->name('notifications.open');
Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])
    ->name('notifications.read-all');
Route::get('/tasks/{crmTask}/open', [NotificationCenterController::class, 'openTask'])
    ->name('notification-tasks.open');
Route::patch('/tasks/{crmTask}/complete', [NotificationCenterController::class, 'completeTask'])
    ->name('notification-tasks.complete');
