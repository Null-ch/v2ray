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
    Route::get('test-new-login', [XuiController::class, 'testNewXuiLogin']);
    Route::get('test-new-inbounds', [XuiController::class, 'testNewXuiInbounds']);

    Route::prefix('{tag}')->group(function () {
        Route::get('server/status', [XuiController::class, 'serverStatus']);
        Route::get('inbounds', [XuiController::class, 'inbounds']);
        Route::get('outbounds', [XuiController::class, 'outbounds']);
        Route::get('panel/settings', [XuiController::class, 'panelSettings']);
    });
});