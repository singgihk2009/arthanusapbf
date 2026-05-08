<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class WarehouseTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
                $allowed = auth()->user()?->allowedWarehouseIds() ?? [];

        return [
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:to_warehouse_id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'document_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'lines.*.qty_requested' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $user = $this->user();
            if ($user && ! $user->hasRole(['super-admin', 'Admin', 'Super Admin'])) {
                $allowed = $user->allowedWarehouseIds();
                if (! in_array((int) $this->input('from_warehouse_id'), $allowed, true)) { $validator->errors()->add('from_warehouse_id', 'Gudang asal tidak diizinkan.'); }
                if (! in_array((int) $this->input('to_warehouse_id'), $allowed, true)) { $validator->errors()->add('to_warehouse_id', 'Gudang tujuan tidak diizinkan.'); }
            }
            $lines = $this->input('lines', []);

            foreach ($lines as $index => $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $batchId = (int) ($line['batch_id'] ?? 0);

                if (! $batchId) {
                    continue;
                }

                $isValidBatch = DB::table('item_batches')
                    ->where('id', $batchId)
                    ->where('item_id', $itemId)
                    ->exists();

                if (! $isValidBatch) {
                    $validator->errors()->add("lines.$index.batch_id", 'Batch tidak sesuai dengan item yang dipilih.');
                }
            }
        });
    }
}
