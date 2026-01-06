<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CockpitController;
use App\Http\Controllers\Cockpit\CockpitXuiController;
use App\Http\Controllers\Cockpit\CockpitUserController;
use App\Http\Controllers\Cockpit\CockpitBalanceController;
use App\Http\Controllers\Cockpit\CockpitReferralController;
use App\Http\Controllers\Cockpit\CockpitPricingController;
use App\Http\Controllers\Cockpit\CockpitSubscriptionController;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// Cockpit routes
Route::prefix('cockpit')->group(function () {
    Route::middleware('guest:cockpit')->group(function () {
        Route::get('/login', [CockpitController::class, 'showLoginForm'])->name('cockpit.login');
        Route::post('/login', [CockpitController::class, 'login'])->name('cockpit.login.post');
    });

    Route::post('/logout', [CockpitController::class, 'logout'])->name('cockpit.logout');

    Route::middleware(['cockpit.auth'])->name('cockpit.')->group(function () {
        Route::get('/', [CockpitController::class, 'dashboard'])->name('dashboard');

        // XUI CRUD routes
        Route::resource('xui', CockpitXuiController::class);

        // User CRUD routes
        Route::resource('user', CockpitUserController::class);

        // Balance CRUD routes
        Route::resource('balance', CockpitBalanceController::class);

        // Referral CRUD routes
        Route::resource('referral', CockpitReferralController::class);

        // Pricing CRUD routes
        Route::resource('pricing', CockpitPricingController::class);

        // Subscription CRUD routes
        Route::resource('subscription', CockpitSubscriptionController::class);
    });
});

// Payment routes
Route::prefix('payment')->group(function () {
    Route::get('/', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/', [PaymentController::class, 'create'])->name('payment.create');
    Route::get('/{payment}/return', [PaymentController::class, 'return'])->name('payment.return');
    Route::get('/{payment}/status', [PaymentController::class, 'status'])->name('payment.status');
});
