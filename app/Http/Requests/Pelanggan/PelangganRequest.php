<?php

namespace App\Http\Requests\Pelanggan;

use App\Enums\UserPermission;
use Illuminate\Contracts\Validation\Rule as RuleContract;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PelangganRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(UserPermission::PelangganUpdate->value);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, RuleContract|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z]/'],
            'email' => ['nullable', 'email', 'max:255'],
            'whatsapp' => ['required', 'string', 'max:20', 'regex:/^(?:\+62|0)8\d{7,11}$/'],
            'address' => ['required', 'string', 'max:500'],
            'maps_url' => ['nullable', 'string', 'max:2000'],
            'area_code_id' => ['required', Rule::exists('area_codes', 'id')],
            'package_id' => ['required', Rule::exists('packages', 'id')],
            'ppp_secret_id' => [
                'nullable',
                Rule::exists('ppp_secrets', 'id'),
                Rule::unique('pelanggans', 'ppp_secret_id')->ignore($this->route('pelanggan')?->getRouteKey()),
            ],
            'status' => ['required', Rule::in(['aktif', 'berhenti'])],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'name.regex' => __('Nama harus diawali huruf.'),
            'ppp_secret_id.unique' => __('Akun PPPoE tersebut sudah dipakai pelanggan lain.'),
        ];
    }
}
