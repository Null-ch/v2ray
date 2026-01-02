<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tg_tag' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'tg_id' => ['required', 'integer'],
            'uuid' => ['required', 'uuid'],
            'referrer_id' => ['nullable', 'integer', 'exists:users,id'],
            'referral_code' => ['nullable', 'string', 'max:255', 'unique:users,referral_code'],
        ];
    }
}

