<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaregiverController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\SberPaymentController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\WalletTopUpController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/caregivers', [HomeController::class, 'caregivers'])->name('caregivers.index');
Route::get('/caregivers/{caregiverProfile}', [HomeController::class, 'showCaregiver'])->name('caregivers.show');
Route::get('/news', [NewsController::class, 'index'])->name('news.index');

Route::post('/payments/sber/callback', [SberPaymentController::class, 'callback'])
    ->name('payments.sber.callback');
Route::get('/payments/sber/return/{walletTopUp}', [SberPaymentController::class, 'returnResult'])
    ->whereUuid('walletTopUp')
    ->name('payments.sber.return');
Route::get('/payments/sber/fail/{walletTopUp}', [SberPaymentController::class, 'failResult'])
    ->whereUuid('walletTopUp')
    ->name('payments.sber.fail');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.store');
    Route::get('/register/captcha', [AuthController::class, 'refreshCaptcha'])
        ->middleware('throttle:20,1')
        ->name('register.captcha');
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['vk', 'yandex'])
        ->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['vk', 'yandex'])
        ->name('social.callback');
    Route::get('/auth/social/complete', [SocialAuthController::class, 'showCompleteRegistration'])->name('social.complete');
    Route::post('/auth/social/complete', [SocialAuthController::class, 'completeRegistration'])->name('social.complete.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])
        ->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:3,1')
        ->name('verification.send');

    Route::middleware(['verified', 'role:caregiver'])->group(function () {
        Route::get('/cabinet/caregiver', [CaregiverController::class, 'myDashboard'])->name('caregiver.dashboard');
        Route::get('/cabinet/caregiver/orders/{order}', [CaregiverController::class, 'showOrder'])->name('caregiver.orders.show');
        Route::get('/cabinet/caregiver/payouts', [CaregiverController::class, 'payoutsHistory'])->name('caregiver.payouts.index');
        Route::post('/cabinet/caregiver/profile', [CaregiverController::class, 'updateProfile'])->name('caregiver.profile.update');
        Route::post('/cabinet/caregiver/orders/{order}/accept', [CaregiverController::class, 'acceptOrder'])->name('caregiver.orders.accept');
        Route::post('/cabinet/caregiver/orders/{order}/decline', [CaregiverController::class, 'declineOrder'])->name('caregiver.orders.decline');
        Route::post('/cabinet/caregiver/orders/{order}/cancel', [CaregiverController::class, 'cancelOrder'])->name('caregiver.orders.cancel');
        Route::post('/cabinet/caregiver/orders/{order}/expenses', [CaregiverController::class, 'storeExpense'])->name('caregiver.orders.expenses.store');
        Route::post('/cabinet/caregiver/orders/{order}/review', [CaregiverController::class, 'storeReview'])->name('caregiver.orders.review.store');
        Route::post('/cabinet/caregiver/orders/{order}/messages', [CaregiverController::class, 'storeMessage'])->name('caregiver.orders.messages.store');
        Route::post('/cabinet/caregiver/orders/{order}/messages/read', [CaregiverController::class, 'markMessagesRead'])->name('caregiver.orders.messages.read');
        Route::get('/cabinet/caregiver/legal', [ContractController::class, 'caregiverLegal'])->name('caregiver.legal');
        Route::get('/cabinet/caregiver/agreement', [ContractController::class, 'caregiverAgreement'])->name('contracts.caregiver.preview');
    });

    Route::middleware(['verified', 'role:client'])->group(function () {
        Route::get('/cabinet/client', [ClientController::class, 'myDashboard'])->name('client.dashboard');
        Route::get('/cabinet/client/orders/{order}', [ClientController::class, 'showOrder'])->name('client.orders.show');
        Route::get('/cabinet/client/orders/create', [ClientController::class, 'createOrder'])->name('client.orders.create');
        Route::get('/cabinet/client/orders/{order}/extend', [ClientController::class, 'extendOrder'])->name('client.orders.extend');
        Route::get('/cabinet/client/payments', [ClientController::class, 'paymentsHistory'])->name('client.payments.index');
        Route::get('/caregivers/{caregiverProfile}/order', [ClientController::class, 'createOrderForCaregiver'])->name('client.orders.create_for_caregiver');
        Route::post('/cabinet/client/orders', [ClientController::class, 'storeOrder'])->name('client.orders.store');
        Route::post('/cabinet/client/wallet/top-up', [WalletTopUpController::class, 'store'])->name('client.wallet.topup');
        Route::post('/cabinet/client/orders/{order}/start', [ClientController::class, 'startOrder'])->name('client.orders.start');
        Route::post('/cabinet/client/orders/{order}/complete', [ClientController::class, 'completeOrder'])->name('client.orders.complete');
        Route::post('/cabinet/client/orders/{order}/cancel', [ClientController::class, 'cancelOrder'])->name('client.orders.cancel');
        Route::post('/cabinet/client/orders/{order}/expenses/{expense}/approve', [ClientController::class, 'approveExpense'])->name('client.orders.expenses.approve');
        Route::post('/cabinet/client/orders/{order}/expenses/{expense}/reject', [ClientController::class, 'rejectExpense'])->name('client.orders.expenses.reject');
        Route::post('/cabinet/client/orders/{order}/review', [ClientController::class, 'storeReview'])->name('client.orders.review.store');
        Route::post('/cabinet/client/orders/{order}/invite/{caregiverProfile}', [ClientController::class, 'inviteCaregiver'])->name('client.orders.invite');
        Route::post('/cabinet/client/orders/{order}/messages', [ClientController::class, 'storeMessage'])->name('client.orders.messages.store');
        Route::post('/cabinet/client/orders/{order}/messages/read', [ClientController::class, 'markMessagesRead'])->name('client.orders.messages.read');
        Route::post('/cabinet/client/family-members', [ClientController::class, 'storeFamilyMember'])->name('client.family.store');
        Route::post('/cabinet/client/templates', [ClientController::class, 'storeTemplate'])->name('client.templates.store');
        Route::get('/cabinet/client/legal', [ContractController::class, 'clientLegal'])->name('client.legal');
        Route::get('/cabinet/client/agreement', [ContractController::class, 'clientAgreement'])->name('contracts.client.preview');
    });

    Route::middleware('verified')->group(function () {
        Route::post('/cabinet/legal/profile', [ContractController::class, 'updateProfile'])->name('contracts.profile.update');
        Route::post('/cabinet/legal/document', [ContractController::class, 'storeDocument'])->name('contracts.document.store');
    });

    Route::middleware(['verified', 'role:admin'])->group(function () {
        Route::get('/admin', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::post('/admin/services', [AdminController::class, 'storeService'])->name('admin.services.store');
        Route::post('/admin/news', [AdminController::class, 'storeNews'])->name('admin.news.store');
        Route::post('/admin/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
        Route::get('/dashboard/caregiver/{user}', [CaregiverController::class, 'dashboard'])->name('dashboard.caregiver');
        Route::get('/dashboard/client/{user}', [ClientController::class, 'dashboard'])->name('dashboard.client');
    });
});
