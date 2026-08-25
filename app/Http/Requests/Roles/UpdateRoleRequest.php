<?php

namespace App\Http\Requests\Roles;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var Role $targetRole */
        $targetRole = $this->route('role');

        if ($targetRole->name === UserRole::SuperAdmin->value) {
            return false;
        }

        return $this->user()->can(UserPermission::RolesUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Closure|RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var Role $targetRole */
        $targetRole = $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($targetRole),
                $this->protectedRoleRule(),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Prevent actors from renaming the built-in superadmin role.
     */
    protected function protectedRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            /** @var Role $targetRole */
            $targetRole = $this->route('role');

            if ($targetRole->name === UserRole::SuperAdmin->value && $value !== UserRole::SuperAdmin->value) {
                $fail(__('Role superadmin tidak dapat diubah.'));
            }
        };
    }
}
