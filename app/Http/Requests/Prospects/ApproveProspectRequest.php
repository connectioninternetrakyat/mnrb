<?php

namespace App\Http\Requests\Prospects;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveProspectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::ProspectsApprove->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.after' => __('Jadwal pemasangan harus lebih dari waktu sekarang.'),
            'user_id.required' => __('Petugas / teknisi wajib dipilih.'),
        ];
    }
}
