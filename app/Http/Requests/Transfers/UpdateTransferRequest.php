<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('transfer')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'expires_in_days' => ['sometimes', 'integer', Rule::in(config('transfers.expiry_options'))],
            'notify_on_download' => ['sometimes', 'boolean'],
            'remove_password' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Titel',
            'message' => 'Nachricht',
            'expires_in_days' => 'Ablauf',
            'password' => 'Passwort',
        ];
    }
}
