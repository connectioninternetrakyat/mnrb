<?php

namespace App\Http\Requests\Roles;

use App\Enums\MenuPermission;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use Closure;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class SyncRolePermissionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::RolesUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Closure|RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'menus' => ['present', 'array'],
            'menus.*' => [
                'string',
                Rule::in(MenuPermission::values()),
            ],
            ...$this->protectedRoleRule(),
        ];
    }

    /**
     * Get the validation rule that protects the built-in superadmin role.
     *
     * @return array<int, Closure|ValidationRule>
     */
    protected function protectedRoleRule(): array
    {
        return [
            function (string $attribute, mixed $value, Closure $fail): void {
                /** @var Role|null $targetRole */
                $targetRole = $this->route('role');

                if ($targetRole !== null && $targetRole->name === UserRole::SuperAdmin->value) {
                    $fail(__('Menu untuk role superadmin tidak dapat diubah.'));
                }
            },
        ];
    }
}
