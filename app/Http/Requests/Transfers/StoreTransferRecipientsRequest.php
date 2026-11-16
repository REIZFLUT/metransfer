<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRecipientsRequest extends FormRequest
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
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'recipients' => 'Empfänger',
            'recipients.*' => 'Empfänger-Adresse',
        ];
    }
}
