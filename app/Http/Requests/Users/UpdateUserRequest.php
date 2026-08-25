<?php

namespace App\Http\Requests\Users;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::UsersUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Closure|Password|RuleContract|Unique|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var User $targetUser */
        $targetUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($targetUser),
            ],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9\s\-()]{8,20}$/'],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name'),
                $this->assignableRoleRule(),
            ],
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
