<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::UsersCreate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Closure|Password|RuleContract|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'avatar' => $this->avatarRules(),
            'whatsapp' => $this->whatsappRules(),
            'role' => $this->roleRules(),
        ];
    }

    /**
     * Get the validation rules used to validate the assigned role.
     *
     * @return array<int, Closure|RuleContract|ValidationRule|array<mixed>|string>
     */
    protected function roleRules(): array
    {
        return [
            'required',
            'string',
            Rule::exists('roles', 'name'),
            $this->assignableRoleRule(),
        ];
    }

    /**
     * Prevent actors without the superadmin role from assigning it.
     */
    protected function assignableRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === UserRole::SuperAdmin->value && ! $this->user()->hasRole(UserRole::SuperAdmin->value)) {
                $fail(__('You are not allowed to assign this role.'));
            }
        };
    }
}
