<?php

namespace App\Http\Requests\Tickets;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PsbFinishRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::TicketsUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'check_pppoe_account' => ['accepted'],
            'check_customer_active' => ['accepted'],
            'check_vlan_tr069' => ['accepted'],
            'finish_note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::in(['tunai', 'transfer'])],
            'bank' => ['nullable', 'string', Rule::in(['bri', 'bni', 'bca', 'mandiri'])],
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
            'check_pppoe_account.accepted' => __('Akun PPPoE harus sudah benar sebelum tiket ditutup.'),
            'check_customer_active.accepted' => __('Akun pelanggan harus sudah aktif sebelum tiket ditutup.'),
            'check_vlan_tr069.accepted' => __('VLAN 100/99 & TR-069/ACS harus sudah sesuai sebelum tiket ditutup.'),
        ];
    }
}
