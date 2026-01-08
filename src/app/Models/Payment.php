<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonImmutable;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_CANCELING = 'canceling';

    protected $fillable = [
        'user_id',
        'yookassa_payment_id',
        'amount',
        'description',
        'status',
        'yookassa_status',
        'confirmation_url',
        'telegram_message_id',
        'provider_payment_charge_id',
        'telegram_payment_charge_id',
        'metadata',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Владелец платежа
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Проверяет, является ли платеж успешным
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * Проверяет, является ли платеж отмененным
     */
    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED || $this->status === self::STATUS_CANCELING;
    }

    /**
     * Проверяет, находится ли платеж в ожидании
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function getPricingId(): ?int
    {
        return isset($this->metadata['pricing_id'])
            ? (int) $this->metadata['pricing_id']
            : null;
    }

    public function getVpnTag(): ?string
    {
        return $this->metadata['vpn_tag'] ?? null;
    }

    public function getDuration(): ?int
    {
        return isset($this->metadata['duration'])
            ? (int) $this->metadata['duration']
            : null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getProviderPaimentChargeId()
    {
        return $this->provider_payment_charge_id;
    }

    public function getTelegramPaimentChargeId()
    {
        return $this->telegram_payment_charge_id;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getTelegramMessageId()
    {
        return $this->telegram_message_id;
    }

    public function scopeExpiredPending(Builder $query): Builder
    {
        $utcNow = CarbonImmutable::now('UTC');
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('created_at', '<=', $utcNow->subMinutes(10));
    }
}
