<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XuiTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    public static function activeLabelsWithIcons(): array
    {
        $records = self::where('is_active', true)->get();

        $flags = [
            'NL' => '🇳🇱',
            'DE' => '🇩🇪',
            'RU' => '🇷🇺',
            'GB' => '🇬🇧',
            'US' => '🇺🇸',
            'CA' => '🇨🇦',
            'AU' => '🇦🇺',
            'NZ' => '🇳🇿',
            'SG' => '🇸🇬',
            'HK' => '🇭🇰',
            'JP' => '🇯🇵',
            'KR' => '🇰🇷',
            'CN' => '🇨🇳',
            'TW' => '🇹🇼',
        ];

        return $records->map(function (self $xui) use ($flags) {
            $emoji = $flags[$xui->tag->value] ?? '';
            return [
                'label' => trim($emoji . ' ' . $xui->tagLabel),
                'tag' => $xui->tag->value,
            ];
        })->toArray();
    }
}
