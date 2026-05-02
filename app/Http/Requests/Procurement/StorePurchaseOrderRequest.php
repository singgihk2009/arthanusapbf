<?php

namespace App\Http\Requests\Procurement;

use App\Models\Procurement\Vendor;
use App\Services\VendorComplianceService;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vendor_id' => ['nullable', 'integer'],
            'allow_unqualified_vendor' => ['nullable', 'boolean'],
            'override_reason' => ['required_if:allow_unqualified_vendor,true', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->vendor_id) return;
            $vendor = Vendor::find($this->vendor_id);
            if (!$vendor) return;
            $summary = app(VendorComplianceService::class)->evaluate($vendor);
            if (!$summary['can_create_po'] && !$this->boolean('allow_unqualified_vendor')) {
                $validator->errors()->add('vendor_id', 'Vendor tidak dapat digunakan untuk PO karena compliance belum valid: '.implode(', ', $summary['blocking_reasons']));
            }
        });
    }
}
