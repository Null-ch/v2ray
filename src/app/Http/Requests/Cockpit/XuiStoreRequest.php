<?php

declare(strict_types=1);

namespace App\Http\Requests\Cockpit;

use App\Enums\XuiTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class XuiStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag' => ['required', 'string', Rule::in(XuiTag::values())],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'path' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'ssl' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}

