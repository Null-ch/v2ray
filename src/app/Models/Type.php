<?php

namespace App\Models;

use App\Models\Configuration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Type extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    /**
     * Configurations with this type
     */
    public function configurations()
    {
        return $this->hasMany(Configuration::class);
    }
}
