<?php

namespace App\Http\Requests\Ppp;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PppProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::PppUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'local_address' => ['nullable', 'string', 'max:100'],
            'remote_address' => ['nullable', 'string', 'max:100'],
            'dns_server' => ['nullable', 'string', 'max:255'],
            'rate_limit' => ['nullable', 'string', 'max:100'],
        ];
    }
}
