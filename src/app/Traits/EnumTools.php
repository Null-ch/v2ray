<?php

declare(strict_types=1);

namespace App\Traits;

trait EnumTools
{
    public static function fromName(string $name): int
    {
        foreach (self::cases() as $case) {
            if ($name === $case->name) {
                return $case->value;
            }
        }
        throw new \ValueError(
            sprintf('Value "%s" is not found in enum cases %s', $name, self::class)
        );
    }

    public static function getByValue(mixed $value): string
    {
        foreach (self::cases() as $case) {
            if($value === $case->value){
                return $case->name;
            }
        }
        throw new \ValueError(
            sprintf('Value "%s" not found in enum %s', $value, self::class)
        );
    }

    public static function getValues(): array
    {
        $values = [];
        foreach (self::cases() as $case) {
            $values[$case->name] = $case->value;
        }

        return $values;
    }

    public static function getList(): array
    {
        $values = [];

        foreach (self::cases() as $case) {
            $values[] = [
                'id' => $case->value,
                'name' => $case->label()
            ];
        }

        return $values;
    }
}
