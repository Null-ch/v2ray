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

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

            $this->vpnConnectionService->sendWelcomeMessage($bot, $username, $keyboard);
        });

        // Обработчик нажатия на кнопку "ПОДКЛЮЧИТЬ ВПН"
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            // Создаем клавиатуру для инструкции с 5 кнопками
            $instructionsKeyboard = InlineKeyboardMarkup::make()
                ->addRow(
                    InlineKeyboardButton::make('Приложение для Android', url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                )
                ->addRow(
                    InlineKeyboardButton::make('Приложение для iOS', url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                )
                ->addRow(
                    InlineKeyboardButton::make('Для Windows', url: 'https://telegra.ph/Instrukciya-VPN-Windows-01-01')
                )
                ->addRow(
                    InlineKeyboardButton::make('Перенести ключ в приложение', url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
                )
                ->addRow(
                    InlineKeyboardButton::make('Вернуться в главное меню', callback_data: 'main_menu')
                );

            $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $instructionsKeyboard);

            // Сохраняем ID сообщений в глобальные данные пользователя
            $bot->setGlobalData('vpn_message_ids', $messageIds);

            // Отвечаем на callback, чтобы убрать "часики" на кнопке
            $bot->answerCallbackQuery();
        });

        // Обработчик кнопки "Вернуться в главное меню"
        $this->bot->onCallbackQueryData('main_menu', function (Nutgram $bot) {
            // Получаем ID сообщений для удаления
            $messageIds = $bot->getGlobalData('vpn_message_ids', []);

            // Удаляем сообщения (поздравление, ключ, инструкция)
            foreach ($messageIds as $messageId) {
                try {
                    $bot->deleteMessage($bot->chatId(), $messageId);
                } catch (\Throwable $e) {
                    // Игнорируем ошибки удаления
                }
            }

            // Отправляем сообщение "РАДЫ ВАС СНОВА ВИДЕТЬ"
            $username = $bot->user()->username;

            $keyboard = InlineKeyboardMarkup::make()
                ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

            $this->vpnConnectionService->sendWelcomeBackMessage($bot, $username, $keyboard);

            // Отвечаем на callback
            $bot->answerCallbackQuery();
        });

        // Пример обработчика команды /help
        $this->bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage('Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь');
        });
    }
}

