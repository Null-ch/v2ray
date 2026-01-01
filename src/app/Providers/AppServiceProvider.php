<?php

namespace App\Providers;

use SergiX44\Nutgram\Nutgram;
use App\Services\TelegramService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TelegramService::class, function ($app) {
            return new TelegramService();
        });

        $this->app->singleton(Nutgram::class, function ($app) {
            return $app->make(TelegramService::class)->getBot();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Пример регистрации обработчиков Telegram бота:
        // $bot = $this->app->make(Nutgram::class);
        // $bot->onCommand('start', function (Nutgram $bot) {
        //     $bot->sendMessage('Привет!');
        // });
        //
        // Или используйте TelegramBotHandlers:
        // $handlers = new \App\Services\TelegramBotHandlers($bot);
        // $handlers->registerHandlers();
    }
}
