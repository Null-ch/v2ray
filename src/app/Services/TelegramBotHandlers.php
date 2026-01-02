<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\User\UserService;
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
        private VpnConnectionService $vpnConnectionService,
        private UserService $userService
    ) {
    }

    public function registerHandlers(): void
    {
        // Обработчик команды /start
        $this->bot->onCommand('start', function (Nutgram $bot) {
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if (!$user) {
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('✅Принять', callback_data: 'accept_terms'));

                $this->vpnConnectionService->sendWelcomeMessageForNewUser($bot, $keyboard);
            } else {
                // Существующий пользователь - показываем главное меню
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            }
        });

        // Обработчик нажатия на кнопку "Принять" для нового пользователя
        $this->bot->onCallbackQueryData('accept_terms', function (Nutgram $bot) {
            $telegramId = $bot->userId();
            $username = $bot->user()->username;
            $name = $bot->user()->first_name;

            // Создаем пользователя в БД
            $this->userService->createUser($telegramId, $username, $name);

            // Генерируем конфиг (заглушка)
            $this->userService->generateVpnConfig();

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

            $bot->setGlobalData('vpn_message_ids', $messageIds);

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

            // Получаем пользователя из БД
            $telegramId = $bot->userId();
            $user = $this->userService->findUserByTelegramId($telegramId);

            if ($user) {
                // Отправляем главное меню с балансом
                $keyboard = InlineKeyboardMarkup::make()
                    ->addRow(InlineKeyboardButton::make('ПОДКЛЮЧИТЬ ВПН', callback_data: 'connect_vpn'));

                $this->vpnConnectionService->sendMainMenu($bot, $user, $keyboard);
            }

            // Отвечаем на callback
            $bot->answerCallbackQuery();
        });

        // Пример обработчика команды /help
        $this->bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage('Доступные команды:' . PHP_EOL . '/start - Начать работу' . PHP_EOL . '/help - Помощь');
        });
    }
}

