<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashAccountRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:50', 'unique:cash_accounts,code'],
            'name' => ['required', 'string', 'max:100'],
            'account_type' => ['required', 'in:cash,bank'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_at' => ['nullable', 'date'],
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
            'opening_balance.required' => 'Saldo awal wajib diisi.',
            'opening_balance.min' => 'Saldo awal tidak boleh bernilai negatif.',
        ];
    }
}
