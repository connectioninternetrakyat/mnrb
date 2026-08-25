<?php

namespace App\Http\Requests\Billing;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BillingSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::BillingUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'prorate_cut_day' => ['required', 'integer', 'min:1', 'max:28'],
            'invoice_day' => ['required', 'integer', 'min:1', 'max:28'],
            'isolir_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'isolir_hour' => ['nullable', 'date_format:H:i'],
            'isolir_profile' => ['nullable', 'string', 'max:100'],
            'ppn_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'promo_percent' => ['required', 'integer', 'min:0', 'max:100'],
            // Radix checkboxes only submit when checked; missing = false.
            'remind_h3' => ['boolean'],
            'remind_h2' => ['boolean'],
            'remind_h1' => ['boolean'],
        ];
    }
}
