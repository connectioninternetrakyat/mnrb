<?php

namespace App\Http\Requests\Servers;

use App\Enums\RadiusDriver;
use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRadiusSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ServersUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // FreeRADIUS server
            'server_host' => ['required', 'string', 'max:255'],
            'auth_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'shared_secret' => ['nullable', 'string', 'max:255'],
            'timeout' => ['required', 'integer', 'min:1', 'max:60'],

            // RADIUS database (MySQL / PostgreSQL)
            'db_driver' => ['required', Rule::in(RadiusDriver::values())],
            'db_host' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['nullable', 'string', 'max:100'],
            'db_username' => ['nullable', 'string', 'max:100'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
