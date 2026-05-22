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
            'bank_account_id' => ['nullable','integer','exists:vendor_bank_accounts,id'],
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

    public function messages(): array
    {
        return [
            'payment_date.required' => 'Payment date wajib diisi.',
            'bank_account_id.integer' => 'Bank account harus dipilih dari daftar.',
            'bank_account_id.exists' => 'Bank account tidak valid.',
            'lines.required' => 'Pilih minimal 1 invoice.',
            'lines.min' => 'Pilih minimal 1 invoice.',
            'lines.*.payment_amount.min' => 'Payment amount minimal 0.01.',
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_date' => 'payment date',
            'payment_method' => 'payment method',
            'bank_account_id' => 'bank account',
            'lines' => 'invoice lines',
        ];
    }
}

