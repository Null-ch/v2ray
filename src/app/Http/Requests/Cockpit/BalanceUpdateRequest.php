<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $balanceId = $this->route('balance');

        return [
            'user_id' => ['required', 'integer', 'exists:users,id', Rule::unique('balances', 'user_id')->ignore($balanceId)],
            'balance' => ['required', 'numeric', 'min:0'],
        ];
    }
}

