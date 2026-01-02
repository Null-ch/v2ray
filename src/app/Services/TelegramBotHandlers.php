<?php

declare(strict_types=1);

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

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
final readonly class TelegramBotHandlers
{
    public function __construct(
        private Nutgram $bot,
        private VpnConnectionService $vpnConnectionService
    ) {
    }

    public function registerHandlers(): void
    {
        // Обработчик команды /start
        $this->bot->onCommand('start', function (Nutgram $bot) {
            $username = $bot->user()->username;

            $this->vpnConnectionService->sendWelcomeMessage($bot, $username);

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

            $bot->sendMessage('', reply_markup: $keyboard);
        });

        // Обработчик нажатия на кнопку "ПОДКЛЮЧИТЬ ВПН"
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            $this->vpnConnectionService->sendVpnConnectionMessages($bot);

            // Отвечаем на callback, чтобы убрать "часики" на кнопке
            $bot->answerCallbackQuery();
        });

        // Пример обработчика команды /help
        $this->bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage('Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь');
        });
    }
}

