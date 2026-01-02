<?php

declare(strict_types=1);

namespace App\Services;

use SergiX44\Nutgram\Nutgram;

/**
 * Пример обработчиков команд для Telegram бота
 * 
 * Использование:
 * В AppServiceProvider или в отдельном сервис-провайдере зарегистрируйте обработчики:
 * 
 * $bot = app(Nutgram::class);
 * $bot->onCommand('start', function (Nutgram $bot) {
 *     $bot->sendMessage('Привет! Я бот.');
 * });
 * 
 * Или используйте этот класс для организации обработчиков
 */
final class TelegramBotHandlers
{
    public function __construct(
        private readonly Nutgram $bot
    ) {
    }

    public function registerHandlers(): void
    {
        // Пример обработчика команды /start
        $this->bot->onCommand('start', function (Nutgram $bot) {
            $bot->sendMessage('Привет! Я бот. Используйте /help для списка команд.');
        });

        // Пример обработчика команды /help
        $this->bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage('Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь');
        });

        // Пример обработчика текстовых сообщений
        $this->bot->onText('.*', function (Nutgram $bot) {
            $bot->sendMessage('Вы написали: ' . $bot->message()->text);
        });
    }
}

