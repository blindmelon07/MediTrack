<?php

namespace App\Http\Requests;

use App\Concerns\UserAccountValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use UserAccountValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->account());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->userAccountRules($this->account());
    }

    /**
     * Stop administrators from removing their own admin access and locking everyone out.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('role')) {
                    return;
                }

                if ($this->account()->is($this->user()) && $this->enum('role', Role::class) !== Role::Admin) {
                    $validator->errors()->add('role', 'You cannot remove your own administrator access.');
                }
            },
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->userAccountMessages();
    }

    /**
     * Get the account being updated.
     */
    private function account(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
