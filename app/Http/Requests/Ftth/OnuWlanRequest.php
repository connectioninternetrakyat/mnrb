<?php

namespace App\Http\Requests\Ftth;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OnuWlanRequest extends FormRequest
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
            'ssid' => ['required', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8', 'max:63'],
            'channel' => ['nullable', 'integer', 'between:0,165'],
        ];
    }
}
