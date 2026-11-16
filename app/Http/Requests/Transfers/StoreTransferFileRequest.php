<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferFileRequest extends FormRequest
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
        $maxSize = (int) config('transfers.max_file_size');

        return [
            'name' => ['required', 'string', 'max:255'],
            'size' => array_filter(['required', 'integer', 'min:0', $maxSize > 0 ? 'max:'.$maxSize : null]),
            'mime_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size.max' => 'Die Datei ist zu groß.',
        ];
    }
}
