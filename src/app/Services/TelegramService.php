<?php

declare(strict_types=1);

namespace App\Services;

use SergiX44\Nutgram\Nutgram;

final class TelegramService
{
    private readonly Nutgram $bot;

    public function __construct()
    {
        $token = config('services.telegram.bot_token');
        
        if (empty($token)) {
            throw new \RuntimeException('Telegram bot token is not configured');
        }

        $this->bot = new Nutgram($token);
    }

    public function getBot(): Nutgram
    {
        return $this->bot;
    }

    public function run(): void
    {
        // Регистрируем обработчики на этом экземпляре Nutgram
        $handlers = app(TelegramBotHandlers::class);
        $handlers->registerHandlers();

        // Запускаем long polling
        $this->bot->run();
    }
}

