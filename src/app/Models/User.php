<?php

namespace App\Models;

use App\Models\Balance;
use App\Models\Referral;
use App\Models\Subscription;
use Illuminate\Support\Str;
use App\Models\Configuration;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'tg_tag',
        'tg_id',
        'uuid',
        'referrer_id',
        'referral_code',
        'referral_tag',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tg_id' => 'integer',
        ];
    }

    /**
     * Who invited the user
     */
    public function referrer()
    {
        return $this->belongsTo(self::class, 'referrer_id');
    }

    /**
     * The users that this user invited
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'user_id');
    }

    /**
     * Users invited by this user
     */
    public function referredUsers()
    {
        return $this->belongsToMany(
            self::class,
            'referrals',
            'user_id',
            'referred_user_id'
        );
    }

    /**
     * User balance
     */
    public function balance()
    {
        return $this->hasOne(Balance::class);
    }

    /**
     * User Configurations
     */
    public function configurations()
    {
        return $this->hasMany(Configuration::class);
    }

    /**
     * User subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * User payments
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getVpnEmail()
    {
       return Str::substr($this->uuid, 0, 6) . $this->id;
    }

    public function getTgId()
    {
        return $this->tg_id;
    }
}
