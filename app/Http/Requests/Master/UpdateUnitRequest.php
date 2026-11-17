<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') ?? false;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->id ?? $this->route('unit');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('units', 'name')->ignore($unitId),
            ],
            'symbol' => [
                'required',
                'string',
                'max:20',
                Rule::unique('units', 'symbol')->ignore($unitId),
            ],
            'decimal_precision' => ['required', 'integer', 'between:0,3'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama satuan wajib diisi.',
            'name.unique' => 'Nama satuan sudah digunakan.',
            'symbol.required' => 'Simbol satuan wajib diisi.',
            'symbol.unique' => 'Simbol satuan sudah digunakan.',
            'decimal_precision.required' => 'Presisi desimal wajib ditentukan.',
            'decimal_precision.between' => 'Presisi desimal harus bernilai antara 0 sampai 3 digit.',
        ];
    }
}
