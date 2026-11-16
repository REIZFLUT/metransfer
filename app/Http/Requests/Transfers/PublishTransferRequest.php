<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('transfer')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'recipients' => array_values(array_unique(array_filter(array_map(
                fn ($email) => is_string($email) ? mb_strtolower(trim($email)) : $email,
                (array) $this->input('recipients', []),
            )))),
            'password' => $this->filled('password') ? $this->input('password') : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'expires_in_days' => ['required', 'integer', Rule::in(config('transfers.expiry_options'))],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'notify_on_download' => ['boolean'],
            'recipients' => ['array', 'max:20'],
            'recipients.*' => ['email', 'max:255'],
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
            'recipients' => 'Empfänger',
            'recipients.*' => 'Empfänger-Adresse',
        ];
    }
}
