<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::post('test', [TestController::class, 'create']);
Route::put('test/{id}', [TestController::class, 'update']);

if (!empty(config('services.telegram.bot_token'))) {
    Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle']);
}
