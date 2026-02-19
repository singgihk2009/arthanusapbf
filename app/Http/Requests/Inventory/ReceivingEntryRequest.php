<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivingEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'transaction_code' => ['required', Rule::in(['PEMBELIAN', 'RETUR', 'ADJUSTMENT'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.expired_date' => ['nullable', 'date'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }
}
