<?php

namespace App\Http\Requests\Ftth;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OnuWanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::FtthUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:64'],
            'connection_type' => ['required', 'in:IP_Routed,IP_Bridged'],
            'nat_enabled' => ['nullable', 'boolean'],
            'vlan_id' => ['nullable', 'integer', 'min:1', 'max:4094'],
            'username' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:64'],
        ];
    }
}
