<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $vendorId = $this->route('vendor')?->id;

        return [
            'vendor_code' => ['required', 'string', 'max:100', Rule::unique('vendors', 'vendor_code')->ignore($vendorId)],
            'vendor_name' => ['required', 'string', 'max:255'],
            'vendor_type' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'village' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'is_pkp' => ['boolean'],
            'status' => ['nullable', Rule::in(['prospect','active','inactive','blacklist'])],
            'nib_number' => ['nullable','string','max:100'],
            'company_license_number' => ['nullable','string','max:100'],
            'cdakb_cpakb_certificate_number' => ['nullable','string','max:100'],
            'company_director.name' => ['nullable','string','max:255'],
            'company_director.address' => ['nullable','string'],
            'technical_responsible_person.name' => ['nullable','string','max:255'],
            'technical_responsible_person.address' => ['nullable','string'],
            'technical_responsible_person.license_number' => ['nullable','string','max:100'],
            'technical_responsible_person.email' => ['nullable','email','max:255'],
            'technical_responsible_person.phone' => ['nullable','string','max:50'],
            'documents' => ['nullable', 'array'],
        ];
    }
}
