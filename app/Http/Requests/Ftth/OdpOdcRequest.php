<?php

namespace App\Http\Requests\Ftth;

use App\Enums\UserPermission;
use App\Models\OdpOdc;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OdpOdcRequest extends FormRequest
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
        /** @var OdpOdc|null $odp */
        $odp = $this->route('odp');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('odp_odcs', 'code')->ignore($odp),
            ],
            'name' => ['required', 'string', 'max:255'],
            'area_code_id' => ['required', Rule::exists('area_codes', 'id')],
            'region' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:96'],
            'splitter_ratio' => ['nullable', 'string', 'regex:/^\d+\s*:\s*\d+$/'],
            'maps_url' => ['nullable', 'url', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
