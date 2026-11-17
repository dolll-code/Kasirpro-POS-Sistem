<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store_settings.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->code)) {
            $this->merge([
                'code' => strtoupper(trim($this->code)),
            ]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('cash_account')?->id ?? $this->route('cash_account');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cash_accounts', 'code')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:cash,bank'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode akun kas/bank wajib diisi.',
            'code.unique' => 'Kode akun sudah digunakan.',
            'name.required' => 'Nama akun kas/bank wajib diisi.',
            'account_type.required' => 'Tipe akun wajib dipilih (kas atau bank).',
            'account_type.in' => 'Tipe akun hanya boleh kas atau bank.',
        ];
    }
}
