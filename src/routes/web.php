<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CockpitController;
use App\Http\Controllers\Cockpit\CockpitXuiController;
use App\Http\Controllers\Cockpit\CockpitUserController;
use App\Http\Controllers\Cockpit\CockpitBalanceController;
use App\Http\Controllers\Cockpit\CockpitReferralController;

Route::get('/', function () {
    return view('welcome');
});

// Cockpit routes
Route::prefix('cockpit')->name('cockpit.')->group(function () {
    Route::get('/login', [CockpitController::class, 'showLogin'])->name('login');
    Route::post('/login', [CockpitController::class, 'login'])->name('login.post');
    Route::post('/logout', [CockpitController::class, 'logout'])->name('logout');
    
    Route::middleware(['cockpit.auth'])->group(function () {
        Route::get('/', [CockpitController::class, 'dashboard'])->name('dashboard');
        Route::get('/dashboard', [CockpitController::class, 'dashboard'])->name('dashboard');
        
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
