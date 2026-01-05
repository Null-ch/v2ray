<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XuiController;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::post('test', [TestController::class, 'create']);
Route::put('test/{id}', [TestController::class, 'update']);

if (!empty(config('services.telegram.bot_token'))) {
    Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle']);
}

Route::prefix('xui')->group(function () {
    Route::prefix('{tag}')->group(function () {
        Route::get('server/status', [XuiController::class, 'serverStatus']);

        Route::get('inbounds', [XuiController::class, 'inbounds']);
        Route::get('inbounds/{inboundId}', [XuiController::class, 'getInbound']);

        // Клиенты в инбаундах
        Route::post('inbounds/{inboundId}/clients', [XuiController::class, 'addClient']);
        Route::put('inbounds/{inboundId}/clients', [XuiController::class, 'updateClient']);
        Route::get('inbounds/{inboundId}/clients/traffic', [XuiController::class, 'getClientTrafficByUserId']);
        Route::get('inbounds/{inboundId}/clients/trafficId', [XuiController::class, 'getClientTrafficByUserUuid']);
    });
});

// Payment API routes
Route::prefix('payment')->group(function () {
    Route::post('/', [\App\Http\Controllers\PaymentController::class, 'create'])->name('api.payment.create');
    Route::get('/{payment}/status', [\App\Http\Controllers\PaymentController::class, 'status'])->name('api.payment.status');
    Route::post('/webhook', [\App\Http\Controllers\PaymentController::class, 'webhook'])->name('api.payment.webhook');
});