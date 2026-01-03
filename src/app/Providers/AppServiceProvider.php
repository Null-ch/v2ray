<?php

namespace App\Providers;

use App\Services\TelegramBotHandlers;
use App\Services\VpnConnectionService;
use Illuminate\Support\Facades\Log;
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

            $this->app->singleton(VpnConnectionService::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $token = config('services.telegram.bot_token');

        if (empty($token) || !$this->app->bound(TelegramService::class) || !$this->app->bound(VpnConnectionService::class)) {
            return;
        }

        try {
            Log::info('Initializing Telegram bot handlers');
            $handlers = $this->app->make(TelegramBotHandlers::class);
            $handlers->registerHandlers();
            Log::info('Telegram bot handlers registered successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to register Telegram bot handlers', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Игнорируем ошибки при инициализации бота в тестовом окружении
            if (app()->environment() !== 'testing') {
                throw $e;
            }
        }
    }
}
