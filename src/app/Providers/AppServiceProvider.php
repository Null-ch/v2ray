<?php

namespace App\Providers;

use App\Services\XuiService;
use SergiX44\Nutgram\Nutgram;
use App\Clients\YooKassaClient;
use App\Services\SettingService;
use App\Services\TelegramService;
use App\Services\YooKassaService;
use App\Services\TelegramBotHandlers;
use App\Services\VpnConnectionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
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

        $this->app->singleton(TelegramBotHandlers::class);

        // Регистрируем YooKassa клиент и сервис
        // Важно: регистрируем только если конфигурация настроена
        $shopId = config('services.yookassa.shop_id');
        $secretKey = config('services.yookassa.secret_key');
        
        if (!empty($shopId) && !empty($secretKey)) {
            $this->app->singleton(YooKassaClient::class, function ($app) use ($shopId, $secretKey) {
                return new YooKassaClient($shopId, $secretKey);
            });

            $this->app->singleton(YooKassaService::class, function ($app) {
                return new YooKassaService(
                    $app->make(YooKassaClient::class),
                    $app->make(TelegramService::class),
                    $app->make(XuiService::class)
                );
            });
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

        // Проверяем, зарегистрирован ли YooKassaService (нужен для TelegramBotHandlers)
        if (!$this->app->bound(YooKassaService::class)) {
            // Если YooKassa не настроен, регистрируем заглушку или пропускаем инициализацию
            // В зависимости от требований можно либо выбросить исключение, либо пропустить
            // Для работы бота без платежей можно зарегистрировать заглушку
            return;
        }

        try {
            $handlers = $this->app->make(TelegramBotHandlers::class);
            $handlers->registerHandlers();
            Log::info("Telegram bot commands registered.");
        } catch (\Throwable $e) {
            // Игнорируем ошибки при инициализации бота в тестовом окружении
            if (app()->environment() !== 'testing') {
                throw $e;
            }
        }
    }
}