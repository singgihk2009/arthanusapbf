<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_invoice_no' => ['nullable', 'string', 'max:100'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency_code' => ['nullable', 'string', 'max:3'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'wht_tax_type' => ['nullable', 'string', 'max:50'],
            'wht_tax_rate' => ['nullable', 'numeric', 'min:0'],
            'wht_tax_base_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.source_line_type' => ['required', 'string', 'in:goods_receipt_line,receiving_entry_line'],
            'lines.*.source_line_id' => ['required', 'integer'],
            'lines.*.qty_invoiced' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'documents' => ['nullable', 'array'],
            'documents.*.document_type_id' => ['required_with:documents.*.file', 'nullable', 'integer', 'exists:document_types,id'],
            'documents.*.title' => ['nullable', 'string', 'max:255'],
            'documents.*.document_number' => ['nullable', 'string', 'max:255'],
            'documents.*.issue_date' => ['nullable', 'date'],
            'documents.*.expiry_date' => ['nullable', 'date'],
            'documents.*.notes' => ['nullable', 'string'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
