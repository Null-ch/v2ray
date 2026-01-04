<?php

declare(strict_types=1);

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

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
        $this->bot->run();
    }

    /**
     * Сборка InlineKeyboard по N кнопок на строку с опциональной дополнительной строкой
     *
     * @param array $buttons Массив ['label' => '...', 'callback_data' => '...']
     * @param int $perRow Количество кнопок на строку
     * @param array $extraRows Массив дополнительных строк кнопок, каждая строка — массив InlineKeyboardButton
     */
    public static function buildInlineKeyboard(array $buttons, int $perRow = 2, array $extraRows = []): InlineKeyboardMarkup
    {
        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ($buttons as $item) {
            $row[] = InlineKeyboardButton::make($item['label'], callback_data: $item['callback_data']);

            if (count($row) === $perRow) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if (!empty($row)) {
            $keyboard->addRow(...$row);
        }

        foreach ($extraRows as $extraRow) {
            $keyboard->addRow(...$extraRow);
        }

        return $keyboard;
    }
}

