<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/caregivers', [HomeController::class, 'caregivers'])->name('caregivers.index');
Route::get('/caregivers/{caregiverProfile}', [HomeController::class, 'showCaregiver'])->name('caregivers.show');
Route::get('/demo/caregiver', [HomeController::class, 'demoCaregiver'])->name('demo.caregiver');
Route::get('/demo/client', [HomeController::class, 'demoClient'])->name('demo.client');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('role:caregiver')->group(function () {
        Route::get('/cabinet/caregiver', [CaregiverController::class, 'myDashboard'])->name('caregiver.dashboard');
        Route::post('/cabinet/caregiver/profile', [CaregiverController::class, 'updateProfile'])->name('caregiver.profile.update');
        Route::get('/cabinet/caregiver/legal', [ContractController::class, 'caregiverLegal'])->name('caregiver.legal');
        Route::get('/cabinet/caregiver/agreement', [ContractController::class, 'caregiverAgreement'])->name('contracts.caregiver.preview');
    });

    Route::middleware('role:client')->group(function () {
        Route::get('/cabinet/client', [ClientController::class, 'myDashboard'])->name('client.dashboard');
        Route::post('/cabinet/client/orders', [ClientController::class, 'storeOrder'])->name('client.orders.store');
        Route::post('/cabinet/client/family-members', [ClientController::class, 'storeFamilyMember'])->name('client.family.store');
        Route::post('/cabinet/client/templates', [ClientController::class, 'storeTemplate'])->name('client.templates.store');
        Route::get('/cabinet/client/legal', [ContractController::class, 'clientLegal'])->name('client.legal');
        Route::get('/cabinet/client/agreement', [ContractController::class, 'clientAgreement'])->name('contracts.client.preview');
    });

    Route::post('/cabinet/legal/profile', [ContractController::class, 'updateProfile'])->name('contracts.profile.update');
    Route::post('/cabinet/legal/document', [ContractController::class, 'storeDocument'])->name('contracts.document.store');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/admin/services', [AdminController::class, 'storeService'])->name('admin.services.store');
        Route::post('/admin/news', [AdminController::class, 'storeNews'])->name('admin.news.store');
        Route::post('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    });
});

Route::get('/dashboard/caregiver/{user}', [CaregiverController::class, 'dashboard'])->name('dashboard.caregiver');
Route::get('/dashboard/client/{user}', [ClientController::class, 'dashboard'])->name('dashboard.client');
