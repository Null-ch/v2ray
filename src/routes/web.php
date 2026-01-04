<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XuiController;
use App\Http\Controllers\CockpitController;
use App\Http\Controllers\Cockpit\CockpitXuiController;
use App\Http\Controllers\Cockpit\CockpitUserController;
use App\Http\Controllers\Cockpit\CockpitBalanceController;
use App\Http\Controllers\Cockpit\CockpitReferralController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('config')->group(function () {
    Route::get('{tag}/{uuid}', [XuiController::class, 'getConfigs']);
    Route::get('import', [XuiController::class, 'getConfigImportLink']);
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
    });
});
