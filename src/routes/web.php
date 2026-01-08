<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XuiController;
use App\Http\Controllers\CockpitController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Cockpit\CockpitXuiController;
use App\Http\Controllers\Cockpit\CockpitUserController;
use App\Http\Controllers\Cockpit\CockpitBalanceController;
use App\Http\Controllers\Cockpit\CockpitPricingController;
use App\Http\Controllers\Cockpit\CockpitReferralController;
use App\Http\Controllers\Cockpit\CockpitSubscriptionController;
use App\Http\Controllers\Cockpit\CockpitSettingController;
use App\Http\Controllers\Cockpit\CockpitKeyController;
use App\Http\Controllers\Cockpit\CockpitServerMonitorController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('config')->group(function () {
    Route::get('import', [XuiController::class, 'getConfigImportLink'])->name('export.link');
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
        
        // XUI monitoring routes
        Route::prefix('xui')->name('xui.')->group(function () {
            Route::get('/{id}/status', [CockpitXuiController::class, 'checkStatus'])->name('status');
        });

        // Server monitoring routes
        Route::prefix('server')->name('server.')->group(function () {
            Route::get('/monitor', [CockpitServerMonitorController::class, 'index'])->name('monitor.index');
            Route::get('/monitor/{id}', [CockpitServerMonitorController::class, 'show'])->name('monitor');
            Route::get('/monitor/{id}/status.json', [CockpitServerMonitorController::class, 'status'])->name('monitor.status');
        });

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

        // Settings CRUD routes
        Route::resource('setting', CockpitSettingController::class);

        // Keys management routes
        Route::prefix('key')->name('key.')->group(function () {
            Route::get('/', [CockpitKeyController::class, 'index'])->name('index');
            Route::post('/', [CockpitKeyController::class, 'store'])->name('store');
            Route::delete('/{id}', [CockpitKeyController::class, 'destroy'])->name('destroy');
            Route::post('/{id}/adjust-expiry', [CockpitKeyController::class, 'adjustExpiry'])->name('adjust-expiry');
            Route::post('/sweep-expired', [CockpitKeyController::class, 'sweepExpired'])->name('sweep-expired');
            Route::get('/generate-email', [CockpitKeyController::class, 'generateEmail'])->name('generate-email');
        });

        // Dashboard partials
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/stats.partial', [CockpitController::class, 'dashboardStatsPartial'])->name('stats.partial');
            Route::get('/transactions.partial', [CockpitController::class, 'dashboardTransactionsPartial'])->name('transactions.partial');
            Route::get('/charts.json', [CockpitController::class, 'dashboardChartsJson'])->name('charts.json');
        });

        // User balance adjustment
        Route::post('/user/{id}/balance/adjust', [CockpitUserController::class, 'adjustBalance'])->name('user.balance.adjust');
        
        // Users partial
        Route::get('/users/table.partial', [CockpitUserController::class, 'usersTablePartial'])->name('users.table.partial');
    });
});

// Payment routes
Route::prefix('payment')->group(function () {
    Route::get('/', [PaymentController::class, 'show'])->name('payment.show');
    Route::post('/', [PaymentController::class, 'create'])->name('payment.create');
    Route::get('/{payment}/return', [PaymentController::class, 'return'])->name('payment.return');
    Route::get('/{payment}/status', [PaymentController::class, 'status'])->name('payment.status');
});
