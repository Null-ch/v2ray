<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'xui_id' => ['required', 'integer', 'exists:xuis,id'],
            'uuid' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}


