<?php

namespace Nip\Php\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'max_upload_size' => ['nullable', 'integer', 'min:1', 'max:2048'],
            'max_execution_time' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'opcache_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_upload_size.min' => 'Max upload size must be at least 1 MB.',
            'max_upload_size.max' => 'Max upload size cannot exceed 2048 MB.',
            'max_execution_time.min' => 'Max execution time must be at least 0 seconds.',
            'max_execution_time.max' => 'Max execution time cannot exceed 3600 seconds.',
        ];
    }
}
