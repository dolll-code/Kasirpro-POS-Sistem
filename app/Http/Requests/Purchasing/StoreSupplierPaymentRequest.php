<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasPermission('supplier_payments.create');
    }

    public function rules(): array
    {
        return [
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'method' => ['required', 'string', 'in:cash,bank_transfer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cash_account_id.required' => 'Akun kas/bank sumber pembayaran wajib dipilih.',
            'method.required' => 'Metode pembayaran wajib dipilih.',
            'method.in' => 'Metode pembayaran harus berupa tunai (cash) atau transfer bank.',
            'amount.required' => 'Nominal pembayaran wajib diisi.',
            'amount.gt' => 'Nominal pembayaran harus bernilai lebih dari 0.',
        ];
    }
}
