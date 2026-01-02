<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

class ReferralStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'referred_user_id' => ['required', 'integer', 'exists:users,id', 'different:user_id'],
        ];
    }
}

