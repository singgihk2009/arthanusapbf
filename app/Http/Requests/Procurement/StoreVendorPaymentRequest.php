<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'payment_date' => ['required','date'],
            'payment_method' => ['nullable','string','max:50'],
            'bank_account_id' => ['nullable','integer'],
            'stamp_duty_amount' => ['nullable','numeric','min:0'],
            'freight_amount' => ['nullable','numeric','min:0'],
            'bank_charge_amount' => ['nullable','numeric','min:0'],
            'notes' => ['nullable','string'],
            'lines' => ['required','array','min:1'],
            'lines.*.vendor_invoice_id' => ['required','integer','exists:vendor_invoices,id'],
            'lines.*.payment_amount' => ['required','numeric','min:0.01'],
            'lines.*.wht_amount' => ['nullable','numeric','min:0'],
            'lines.*.notes' => ['nullable','string'],
        ];
    }
}
