<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\XuiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::post('test', [TestController::class, 'create']);
Route::put('test/{id}', [TestController::class, 'update']);

// Telegram webhook routes
if (!empty(config('services.telegram.bot_token'))) {
    Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle']);
    Route::get('telegram/webhook/test', function () {
        \Illuminate\Support\Facades\Log::info('Webhook test endpoint called');
        return response()->json(['status' => 'ok', 'message' => 'Webhook endpoint is accessible']);
    });
}

Route::prefix('xui')->group(function () {
    Route::prefix('{tag}')->group(function () {
        Route::get('server/status', [XuiController::class, 'serverStatus']);
        Route::get('inbounds', [XuiController::class, 'inbounds']);
        Route::get('outbounds', [XuiController::class, 'outbounds']);
        Route::get('panel/settings', [XuiController::class, 'panelSettings']);
    });
});
