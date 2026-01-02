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
        // Регистрируем TelegramService только если токен настроен
        $token = config('services.telegram.bot_token');
        
        if (!empty($token)) {
            $this->app->singleton(TelegramService::class, function ($app) {
                return new TelegramService();
            });

            $this->app->singleton(Nutgram::class, function ($app) {
                return $app->make(TelegramService::class)->getBot();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрируем обработчики только если токен настроен и сервисы зарегистрированы
        $token = config('services.telegram.bot_token');
        
        if (empty($token) || !$this->app->bound(TelegramService::class)) {
            return;
        }

        try {
            $bot = $this->app->make(Nutgram::class);
            $handlers = new \App\Services\TelegramBotHandlers($bot);
            $handlers->registerHandlers();
        } catch (\Throwable $e) {
            // Игнорируем ошибки при инициализации бота в тестовом окружении
            if (app()->environment() !== 'testing') {
                throw $e;
            }
        }
    }
}
