<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store_settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'receipt_paper_width' => ['required', 'in:58,80'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama toko wajib diisi.',
            'receipt_paper_width.required' => 'Ukuran lebar kertas struk wajib dipilih.',
            'receipt_paper_width.in' => 'Lebar kertas struk harus 58 mm atau 80 mm.',
            'logo.image' => 'Berkas logo harus berupa gambar valid.',
            'logo.mimes' => 'Format logo hanya mendukung PNG, JPG, JPEG, atau WEBP.',
            'logo.max' => 'Ukuran berkas logo maksimal 2 MB.',
        ];
    }
}
