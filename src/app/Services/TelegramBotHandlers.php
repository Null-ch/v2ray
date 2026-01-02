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
            try {
                $telegramId = $bot->userId();
                $username = $bot->user()->username;
                $name = $bot->user()->first_name;

                // Создаем пользователя в БД
                $user = $this->userService->createUser($telegramId, $username, $name);

                if (!$user) {
                    $bot->answerCallbackQuery('Ошибка создания пользователя', show_alert: true);
                    return;
                }

                // Генерируем конфиг (заглушка)
                $this->userService->generateVpnConfig();

                $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $this->getInstructionsKeyboard());

                $bot->setGlobalData('vpn_message_ids', $messageIds);

                $bot->answerCallbackQuery();
            } catch (\Throwable $e) {
                $bot->answerCallbackQuery('Произошла ошибка: ' . $e->getMessage(), show_alert: true);
                throw $e;
            }
        });

        // Обработчик нажатия на кнопку "ПОДКЛЮЧИТЬ ВПН" для существующих пользователей
        $this->bot->onCallbackQueryData('connect_vpn', function (Nutgram $bot) {
            $messageIds = $this->vpnConnectionService->sendVpnConnectionMessages($bot, $this->getInstructionsKeyboard());

            // Сохраняем ID сообщений в глобальные данные пользователя
            $bot->setGlobalData('vpn_message_ids', $messageIds);

            // Отвечаем на callback, чтобы убрать "часики" на кнопке
            $bot->answerCallbackQuery();
        });

        // Обработчик кнопки "Перенести в приложение"
        $this->bot->onCallbackQueryData('export_config', function (Nutgram $bot) {
            // TODO: Генерация экспортной ссылки с конфигами
            // Пример: https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=TOKEN&scheme=v2raytun

            $exportUrl = 'https://www.sigmalink.org/redirect/?redirect_to=www.sigmalink.org&token=PLACEHOLDER_TOKEN&scheme=v2raytun';

            $bot->sendMessage(
                "📲 Экспортная ссылка для переноса конфигов:\n\n$exportUrl\n\n⚠️ Внимание: это заглушка, функционал в разработке",
                parse_mode: 'HTML'
            );

            $bot->answerCallbackQuery('Ссылка отправлена');
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

    private function getInstructionsKeyboard(): InlineKeyboardMarkup
    {
        return InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('Приложение для Android', url: 'https://play.google.com/store/apps/details?id=com.v2raytun.android&pcampaignid=web_share')
            )
            ->addRow(
                InlineKeyboardButton::make('Приложение для iPhone/iOS', url: 'https://apps.apple.com/ru/app/v2raytun/id6476628951')
            )
            ->addRow(
                InlineKeyboardButton::make('Инструкция для Windows', url: 'https://telegra.ph/Instrukciya-po-ustanovke-V2raytun-na-PK--Windows-1011-01-02')
            )
            ->addRow(
                InlineKeyboardButton::make('📲 Перенести в приложение', callback_data: 'export_config')
            )
            ->addRow(
                InlineKeyboardButton::make('Вернуться в главное меню', callback_data: 'main_menu')
            );
    }
}
