<?php

namespace App\Helpers;

class MillisecondsHelper
{
    /**
     * Добавляет к текущему времени указанное количество дней и возвращает результат в миллисекундах.
     *
     * @param int $days
     * @return int
     */
    public static function addDaysInMillisecondsToNow(int $days): int
    {
        $nowMs = round(microtime(true) * 1000);
        $msToAdd = $days * 24 * 60 * 60 * 1000;

        return $nowMs + $msToAdd;
    }

    public static function daysToMilliseconds(int $days): int
    {
        return $days * 24 * 60 * 60 * 1000;
    }

    /**
     * Преобразует миллисекунды в дни и часы (разница от текущего времени).
     *
     * @param int|string $milliseconds
     * @return array ['days' => int, 'hours' => int]
     */
    public static function millisecondsToDaysHours(int|string $milliseconds): array
    {
        $nowMs = round(microtime(true) * 1000);
        $diffMs = max(0, (int) $milliseconds - $nowMs);

        $days = floor($diffMs / 1000 / 60 / 60 / 24);
        $hours = floor(($diffMs / 1000 / 60 / 60) % 24);

        return [
            'days' => $days,
            'hours' => $hours,
        ];
    }

    /**
     * Преобразует миллисекунды в полное количество дней.
     *
     * @param int|string $milliseconds
     * @return int
     */
    public static function millisecondsToDays(int|string $milliseconds): int
    {
        return (int) round((int)$milliseconds / 1000 / 60 / 60 / 24);
    }

    public static function getNowInMilliseconds()
    {
        return round(microtime(true) * 1000);
    }
}
