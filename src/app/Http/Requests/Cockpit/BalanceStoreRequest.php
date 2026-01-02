<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;

class BalanceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id', 'unique:balances,user_id'],
            'balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}

