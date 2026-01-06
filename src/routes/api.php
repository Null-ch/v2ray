<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TelegramWebhookController;
use SergiX44\Nutgram\Nutgram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::post('test', [TestController::class, 'create']);
Route::put('test/{id}', [TestController::class, 'update']);

// if (!empty(config('services.telegram.bot_token'))) {
//     Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle']);
// }

Route::post('telegram/webhook', function(Request $request) {
    Log::info('Webhook hit', $request->all());

    $bot = new Nutgram(config('services.telegram.bot_token'));

    $bot->onCommand('start', function(Nutgram $bot) {
        $bot->sendMessage("Привет! Бот работает!");
    });

    $bot->run(); // обязательно запускаем обработку обновлений

    return response()->json(['ok' => true]);
});

// Payment API routes
Route::prefix('payment')->group(function () {
    Route::post('/', [\App\Http\Controllers\PaymentController::class, 'create'])->name('api.payment.create');
    Route::get('/{payment}/status', [\App\Http\Controllers\PaymentController::class, 'status'])->name('api.payment.status');
    Route::post('/webhook', [\App\Http\Controllers\PaymentController::class, 'webhook'])->name('api.payment.webhook');
});