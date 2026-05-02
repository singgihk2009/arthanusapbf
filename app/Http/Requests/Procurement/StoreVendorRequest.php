<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $vendorId = $this->route('vendor')?->id;

        return [
            'vendor_code' => ['required', 'string', 'max:100', Rule::unique('vendors', 'vendor_code')->ignore($vendorId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['ACTIVE', 'INACTIVE'])],
            'currency_code' => ['required', 'string', 'size:3'],
        ];
    }
}
