<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Callback;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

enum XuiTag: string
{
    case NL = 'NL';
    case DE = 'DE';
    case RU = 'RU';
    case GB = 'GB';
    case US = 'US';
    case CA = 'CA';
    case AU = 'AU';
    case NZ = 'NZ';
    case SG = 'SG';
    case HK = 'HK';
    case JP = 'JP';
    case KR = 'KR';
    case CN = 'CN';
    case TW = 'TW';

    public function label(): string
    {
        return match ($this) {
            self::NL => 'Нидерланды',
            self::DE => 'Германия',
            self::RU => 'Россия',
            self::GB => 'Великобритания',
            self::US => 'США',
            self::CA => 'Канада',
            self::AU => 'Австралия',
            self::NZ => 'Новая Зеландия',
            self::SG => 'Сингапур',
            self::HK => 'Гонконг',
            self::JP => 'Япония',
            self::KR => 'Корея',
            self::CN => 'Китай',
            self::TW => 'Тайвань',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    public function flag(): string
    {
        return match ($this) {
            self::NL => '🇳🇱',
            self::DE => '🇩🇪',
            self::RU => '🇷🇺',
            self::GB => '🇬🇧',
            self::US => '🇺🇸',
            self::CA => '🇨🇦',
            self::AU => '🇦🇺',
            self::NZ => '🇳🇿',
            self::SG => '🇸🇬',
            self::HK => '🇭🇰',
            self::JP => '🇯🇵',
            self::KR => '🇰🇷',
            self::CN => '🇨🇳',
            self::TW => '🇹🇼',
        };
    }

    public function labelWithFlag(): string
    {
        return trim($this->flag() . ' ' . $this->label());
    }

    public function toButton(): InlineKeyboardButton
    {
        return InlineKeyboardButton::make(
            text: $this->labelWithFlag(),
            callback_data: Callback::VPN_TAG->with($this->value)
        );
    }

    /**
     * @param string[] $values ['NL', 'DE']
     */
    public static function keyboardFromValues(
        array $values,
        int $perRow = 2
    ): InlineKeyboardMarkup {
        $keyboard = InlineKeyboardMarkup::make();
        $row = [];

        foreach ($values as $value) {
            try {
                $tag = self::from($value);
            } catch (\ValueError) {
                continue;
            }

            $row[] = $tag->toButton();

            if (count($row) === $perRow) {
                $keyboard->addRow(...$row);
                $row = [];
            }
        }

        if ($row) {
            $keyboard->addRow(...$row);
        }

        return $keyboard;
    }
}
