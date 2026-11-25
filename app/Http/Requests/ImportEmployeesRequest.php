<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportEmployeesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // An .xlsx file is a zip archive, so its detected type may be zip.
        return [
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv', 'mimes:xlsx,zip,csv,txt'],
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose an Excel (.xlsx) or CSV file to import.',
            'file.extensions' => 'The file must be an Excel (.xlsx) or CSV file.',
            'file.mimes' => 'The file must be an Excel (.xlsx) or CSV file.',
            'file.max' => 'The file must be 5 MB or smaller.',
        ];
    }
}
