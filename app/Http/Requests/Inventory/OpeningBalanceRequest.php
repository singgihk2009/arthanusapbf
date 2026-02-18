<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'trx_datetime' => ['nullable', 'date'],
        ];
    }
}
