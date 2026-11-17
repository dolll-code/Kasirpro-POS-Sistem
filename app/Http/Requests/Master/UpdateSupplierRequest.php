<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('supplier_code') && is_string($this->supplier_code)) {
            $this->merge([
                'supplier_code' => strtoupper(trim($this->supplier_code)),
            ]);
        }
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')?->id ?? $this->route('supplier');

        return [
            'supplier_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'supplier_code')->ignore($supplierId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_code.required' => 'Kode pemasok wajib diisi.',
            'supplier_code.unique' => 'Kode pemasok sudah terdaftar.',
            'name.required' => 'Nama pemasok wajib diisi.',
            'email.email' => 'Format email pemasok tidak valid.',
        ];
    }
}
