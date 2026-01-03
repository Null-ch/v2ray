<?php

declare(strict_types=1);

namespace App\Enums;

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
        return match($this) {
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
}

