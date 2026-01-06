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
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingService::class);
        
        // Регистрируем TelegramService и Nutgram только если токен настроен
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

        // Регистрируем YooKassa клиент и сервис
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

        if (empty($token) || !$this->app->bound(TelegramService::class)) {
            Log::info("Telegram bot token is not configured or service is not bound.");
            return;
        }

        $bot = $this->app->make(Nutgram::class); // Получаем экземпляр бота

        try {
            // Регистрация команд, если бот доступен
            $bot->onCommand('start', function(Nutgram $bot) {
                $bot->sendMessage("Привет! Бот работает!");
            });

            // Можно добавить другие команды
            $bot->onCommand('help', function(Nutgram $bot) {
                $bot->sendMessage("Доступные команды: /start, /help");
            });

            // Логирование всех входящих сообщений
            $bot->onMessage(function(Nutgram $bot) {
                Log::info('Telegram message received', [
                    'from' => $bot->user(),
                    'text' => $bot->message()?->text,
                ]);
            });

            // Логируем успешную инициализацию бота
            Log::info("Telegram bot initialized and commands registered.");
            
        } catch (\Throwable $e) {
            Log::error("Error while registering commands: " . $e->getMessage());
        }

        // Инициализация других сервисов и обработчиков
        if ($this->app->bound(YooKassaService::class)) {
            try {
                $handlers = $this->app->make(TelegramBotHandlers::class);
                $handlers->registerHandlers();
            } catch (\Throwable $e) {
                Log::error("Error initializing TelegramBotHandlers: " . $e->getMessage());
            }
        }
    }
}
