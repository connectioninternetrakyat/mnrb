<?php

namespace App\Http\Requests\Packages;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::PackagesUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        $package = $this->route('package');

        $uniqueProfile = Rule::unique('packages', 'ppp_profile_id');

        if ($package !== null) {
            $uniqueProfile->ignore($package->getRouteKey());
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'ppp_profile_id' => [
                'required',
                'integer',
                Rule::exists('ppp_profiles', 'id'),
                $uniqueProfile,
            ],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
