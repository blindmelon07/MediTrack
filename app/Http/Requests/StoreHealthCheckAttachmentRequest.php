<?php

namespace App\Http\Requests;

use App\Concerns\HealthCheckValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreHealthCheckAttachmentRequest extends FormRequest
{
    use HealthCheckValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->attachmentRules(required: true);
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->healthCheckMessages(),
            'attachments.required' => 'Choose at least one file to upload.',
        ];
    }
}
