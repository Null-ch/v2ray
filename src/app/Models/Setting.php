<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_system',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Возвращает значение настройки в приведенном типе.
     */
    public function getTypedValue(): mixed
    {
        $raw = $this->value;

        return match ($this->type) {
            'int', 'integer' => (int) $raw,
            'float', 'double' => (float) $raw,
            'bool', 'boolean' => filter_var($raw, FILTER_VALIDATE_BOOL),
            'json', 'array' => $this->decodeJson($raw),
            default => $raw,
        };
    }

    /**
     * Устанавливает значение с учетом типа.
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'json', 'array' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'bool', 'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    private function decodeJson(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            /** @var mixed */
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\Throwable) {
            return null;
        }
    }
}


