<?php

namespace App\Http\Requests\Ftth;

use App\Enums\UserPermission;
use App\Models\AreaCode;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaCodeRequest extends FormRequest
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
        /** @var AreaCode|null $areaCode */
        $areaCode = $this->route('area_code');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('area_codes', 'code')->ignore($areaCode),
            ],
            'name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
