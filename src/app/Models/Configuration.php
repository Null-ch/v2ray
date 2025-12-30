<?php

namespace App\Models;

use App\Models\Type;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Configuration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type_id',
        'uri',
    ];

    /**
     * Configuration Owner
     */
    public function user()
    {
        return $this->belongsTo(related: User::class);
    }

    /**
     * Configuration type
     */
    public function type()
    {
        return $this->belongsTo(Type::class);
    }
}
