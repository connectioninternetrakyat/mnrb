<?php

namespace App\Http\Requests\Ppp;

use App\Enums\UserPermission;
use App\Models\PppSecret;
use Closure;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PppSecretRequest extends FormRequest
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
     * @return array<string, array<int, Closure|RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var PppSecret|null $secret */
        $secret = $this->route('secret');

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9@._-]+$/',
                Rule::unique('ppp_secrets', 'name')->ignore($secret),
            ],
            'password' => [
                $secret === null ? 'required' : 'nullable',
                'string',
                'min:4',
                'max:64',
            ],
            'profile' => ['required', 'string', 'max:100'],
        ];
    }
}
