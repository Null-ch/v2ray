<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\XuiTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tag',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tag' => XuiTag::class,
        ];
    }

    /**
     * User that owns this tag
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

