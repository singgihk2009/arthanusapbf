<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $singleWarehouse = count($this->user()?->allowedWarehouseIds() ?? []) === 1;

        return [
            'warehouse_id' => [$singleWarehouse ? 'nullable' : 'required', 'integer', 'exists:warehouses,id'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user(); if (! $user) return;
            $allowed = $user->allowedWarehouseIds();
            if (count($allowed) === 1 && ! $this->input('warehouse_id')) $this->merge(['warehouse_id' => $allowed[0]]);
            if (! $user->hasRole(['super-admin', 'Admin', 'Super Admin']) && ! in_array((int) $this->input('warehouse_id'), $allowed, true)) {
                $validator->errors()->add('warehouse_id', 'Warehouse tidak diizinkan untuk user login.');
            }
        });
    }
}
