<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class InternalUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'document_date' => ['required', 'date'],
            'transaction_code' => ['required', 'string', 'in:PENJUALAN,RETUR,DAMAGED,SAMPLE,INTERNAL_USE'],
            'department' => ['nullable', 'string', 'max:100'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'lines.*.qty_used' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }
}
