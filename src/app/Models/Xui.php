<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XuiTag;
use App\Enums\Callback;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;

class Xui extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'host',
        'port',
        'path',
        'username',
        'password',
        'ssl',
        'is_active',
        'inbound_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tag' => XuiTag::class,
            'port' => 'integer',
            'ssl' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'encrypted',
            'inbound_id' => 'integer',
        ];
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the tag label.
     */
    public function getTagLabelAttribute(): string
    {
        return $this->tag?->label() ?? '';
    }

    /**
     * Получить все активные Xui-записи
     */
    public static function active()
    {
        return self::query()
            ->where('is_active', true)
            ->get();
    }

    /**
     * Активные Xui, которых нет у пользователя
     *
     * @param array<string|XuiTag> $userTags
     */
    public static function activeNotOwnedByUser(array $userTags)
    {
        $tags = array_map(
            fn($tag) => $tag instanceof XuiTag ? $tag->value : (string) $tag,
            $userTags
        );

        return self::query()
            ->where('is_active', true)
            ->whereNotIn('tag', $tags)
            ->get();
    }

    public static function activeButtons(array $excludeTags = []): array
    {
        return self::query()
            ->where('is_active', true)
            ->get()
            ->map(function (self $xui) use ($excludeTags) {

                try {
                    $tag = XuiTag::from($xui->tag->value);
                } catch (\ValueError) {
                    return null;
                }

                // Пропускаем VPN, которые уже активны у пользователя
                if (in_array($tag->value, $excludeTags, true)) {
                    return null;
                }

                return InlineKeyboardButton::make(
                    text: $tag->labelWithFlag(),
                    callback_data: Callback::VPN_PRICING->with($tag->value)
                );
            })
            ->filter()
            ->values()
            ->toArray();
    }
}
