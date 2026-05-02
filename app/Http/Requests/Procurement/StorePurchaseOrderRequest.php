<?php

namespace App\Http\Requests\Procurement;

use App\Models\Procurement\Vendor;
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
            if ($vendor->status !== 'active' || $vendor->qualification_status !== 'qualified') {
                if (!$this->boolean('allow_unqualified_vendor')) {
                    $validator->errors()->add('vendor_id', 'Vendor belum qualified. Lengkapi proses kualifikasi pemasok terlebih dahulu.');
                }
            }
        });
    }
}
