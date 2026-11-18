<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasPermission('purchases.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'document_discount_total' => ['nullable', 'numeric', 'min:0'],
            'additional_cost_estimate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.ordered_qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Pemasok wajib dipilih.',
            'supplier_id.exists' => 'Pemasok yang dipilih tidak terdaftar di sistem.',
            'order_date.required' => 'Tanggal pemesanan wajib diisi.',
            'expected_date.after_or_equal' => 'Estimasi tanggal kedatangan tidak boleh lebih awal dari tanggal pesanan.',
            'items.required' => 'Purchase Order harus memiliki minimal satu item barang.',
            'items.min' => 'Purchase Order harus memiliki minimal satu item barang.',
            'items.*.product_id.required' => 'Produk wajib dipilih.',
            'items.*.ordered_qty.required' => 'Kuantitas pemesanan wajib diisi.',
            'items.*.ordered_qty.gt' => 'Kuantitas pemesanan harus lebih besar dari 0.',
            'items.*.unit_cost.required' => 'Harga beli satuan wajib diisi.',
            'items.*.unit_cost.min' => 'Harga beli satuan tidak boleh negatif.',
        ];
    }
}
