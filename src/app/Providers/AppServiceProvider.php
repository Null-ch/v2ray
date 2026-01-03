<?php

namespace App\Providers;

use App\Services\VpnConnectionService;
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
        // Handlers should only be registered when the bot command is run,
        // not during application bootstrap to avoid multiple registrations
    }
}
