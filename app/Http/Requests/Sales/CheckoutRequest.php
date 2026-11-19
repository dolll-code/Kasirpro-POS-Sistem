<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('sales.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.line_discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],

            'transaction_discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'transaction_discount_value' => ['nullable', 'numeric', 'min:0'],

            'payment' => ['required', 'array'],
            'payment.method' => ['required', 'string', 'in:cash'],
            'payment.cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
            'payment.cash_tendered' => ['required', 'numeric', 'min:0'],
            'payment.reference_number' => ['nullable', 'string', 'max:100'],

            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:1000'],

            'discount_approval' => ['nullable', 'array'],
            'discount_approval.approver_id' => ['nullable', 'integer', 'exists:users,id'],
            'discount_approval.approver_password' => ['nullable', 'string'],
            'discount_approval.reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang belanja tidak boleh kosong.',
            'items.min' => 'Minimal harus ada 1 barang dalam transaksi.',
            'payment.cash_tendered.required' => 'Nominal uang yang diserahkan wajib diisi.',
            'payment.cash_tendered.min' => 'Nominal uang tidak boleh bernilai negatif.',
        ];
    }
}
