<?php

namespace App\Http\Requests\Ftth;

use App\Enums\UserPermission;
use App\Models\Onu;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnuRequest extends FormRequest
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
        /** @var Onu|null $onu */
        $onu = $this->route('onu');

        return [
            'genieacs_id' => [
                'required',
                'string',
                'max:255',
                Rule::unique('onus', 'genieacs_id')->ignore($onu),
            ],
            'name' => ['required', 'string', 'max:255'],
            'pppoe_user' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
