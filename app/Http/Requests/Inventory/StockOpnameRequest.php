<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockOpnameRequest extends FormRequest
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
            'type' => ['required', 'in:FULL,CYCLE'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'lines.*.counted_qty_base' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
