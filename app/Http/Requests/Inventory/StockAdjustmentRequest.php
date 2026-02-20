<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
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
            'reason_code' => ['required', 'in:OPNAME,DAMAGE,EXPIRED,CORRECTION,OTHER'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'lines.*.qty_adjusted' => ['required', 'numeric', 'not_in:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }
}
