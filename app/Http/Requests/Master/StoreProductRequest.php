<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('sku') && is_string($this->sku)) {
            $this->merge([
                'sku' => strtoupper(trim($this->sku)),
            ]);
        }

        if ($this->has('barcode')) {
            $barcode = trim((string) $this->barcode);
            $this->merge([
                'barcode' => $barcode === '' ? null : $barcode,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where('is_active', true),
            ],
            'sku' => [
                'required',
                'string',
                'max:50',
                'unique:products,sku',
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,barcode',
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Kategori produk wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid atau sedang nonaktif.',
            'unit_id.required' => 'Satuan produk wajib dipilih.',
            'unit_id.exists' => 'Satuan yang dipilih tidak valid atau sedang nonaktif.',
            'sku.required' => 'SKU produk wajib diisi.',
            'sku.unique' => 'SKU produk sudah terdaftar.',
            'barcode.unique' => 'Barcode produk sudah terdaftar.',
            'name.required' => 'Nama produk wajib diisi.',
            'selling_price.required' => 'Harga jual wajib diisi.',
            'selling_price.min' => 'Harga jual tidak boleh bernilai negatif.',
            'minimum_stock.required' => 'Batas minimum stok wajib diisi.',
            'minimum_stock.min' => 'Batas minimum stok tidak boleh bernilai negatif.',
        ];
    }
}
