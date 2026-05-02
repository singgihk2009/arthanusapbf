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
        $submit = $this->boolean('submit_qualification');

        $required = $submit ? ['required'] : ['nullable'];

        return [
            'vendor_code' => ['required', 'string', 'max:100', Rule::unique('vendors', 'vendor_code')->ignore($vendorId)],
            'vendor_name' => array_merge($required, ['string', 'max:255']),
            'vendor_type' => array_merge($required, ['string', 'max:100']),
            'address' => array_merge($required, ['string']),
            'postal_code' => array_merge($required, ['string', 'max:20']),
            'village' => array_merge($required, ['string', 'max:255']),
            'district' => array_merge($required, ['string', 'max:255']),
            'city' => array_merge($required, ['string', 'max:255']),
            'province' => array_merge($required, ['string', 'max:255']),
            'phone' => array_merge($required, ['string', 'max:50']),
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'is_pkp' => ['boolean'],
            'status' => ['required', Rule::in(['prospect','active','inactive','blacklist'])],
            'nib_number' => $submit ? ['required','string','max:100'] : ['nullable','string','max:100'],
            'company_license_number' => $submit ? ['required','string','max:100'] : ['nullable','string','max:100'],
            'cdakb_cpakb_certificate_number' => ['nullable','string','max:100'],
            'company_director.name' => $submit ? ['required','string','max:255'] : ['nullable','string','max:255'],
            'company_director.address' => $submit ? ['required','string'] : ['nullable','string'],
            'technical_responsible_person.name' => $submit ? ['required','string','max:255'] : ['nullable','string','max:255'],
            'technical_responsible_person.address' => $submit ? ['required','string'] : ['nullable','string'],
            'technical_responsible_person.license_number' => $submit ? ['required','string','max:100'] : ['nullable','string','max:100'],
            'technical_responsible_person.email' => $submit ? ['required','email','max:255'] : ['nullable','email','max:255'],
            'technical_responsible_person.phone' => $submit ? ['required','string','max:50'] : ['nullable','string','max:50'],
            'documents' => ['array'],
        ];
    }
}
