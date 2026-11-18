<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;

class PostReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->hasPermission('receipts.post') || $this->user()->hasPermission('receipts.create'));
    }

    public function rules(): array
    {
        return [
            'received_at' => ['nullable', 'date'],
            'capitalizable_additional_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'items.*.physically_received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.accepted_qty' => ['required', 'numeric', 'min:0'],
            'items.*.rejected_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:255'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Daftar penerimaan barang harus diisi.',
            'items.min' => 'Minimal satu item harus diproses dalam penerimaan.',
            'items.*.physically_received_qty.required' => 'Kuantitas fisik diterima wajib diisi.',
            'items.*.accepted_qty.required' => 'Kuantitas disetujui (accepted) wajib diisi.',
        ];
    }
}
