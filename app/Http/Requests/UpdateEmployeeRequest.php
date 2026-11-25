<?php

namespace App\Http\Requests;

use App\Concerns\AssignsHrCompany;
use App\Concerns\EmployeeValidationRules;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    use AssignsHrCompany, EmployeeValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee());
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->assignHrCompany();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->employeeRules($this->employee()->id);
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->employeeMessages();
    }

    /**
     * Get the employee being updated.
     */
    private function employee(): Employee
    {
        /** @var Employee */
        return $this->route('employee');
    }
}
