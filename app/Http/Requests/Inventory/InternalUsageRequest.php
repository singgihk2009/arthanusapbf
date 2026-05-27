<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class InternalUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'facility_scheme_id' => ['required', 'integer', 'exists:facility_schemes,id'],
            'document_date' => ['required', 'date'],
            'transaction_code' => ['required', 'string', 'in:PENJUALAN,RETUR,DAMAGED,SAMPLE,INTERNAL_USE'],
            'outbound_number' => ['nullable', 'string', 'max:100'],
            'sender_receiver_name' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'cost_center' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],

            'mode' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string', 'max:100'],
            'source_id' => ['nullable', 'integer'],
            'source_number' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer'],
            'sale_id' => ['nullable', 'integer'],
            'lines.*.sale_line_id' => ['nullable', 'integer'],
            'lines.*.source_line_id' => ['nullable', 'integer'],
            'lines.*.qty_ordered' => ['nullable', 'numeric'],
            'lines.*.qty_already_shipped' => ['nullable', 'numeric'],
            'lines.*.qty_remaining' => ['nullable', 'numeric'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.batch_id' => ['nullable', 'integer', 'exists:item_batches,id'],
            'lines.*.qty_used' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.notes' => ['nullable', 'string'],
        ];

        if (Schema::hasTable('facility_schemes') && Schema::hasColumn('internal_usages', 'facility_scheme_id')) {
            $rules['facility_scheme_id'] = ['required', 'integer', 'exists:facility_schemes,id'];
        } else {
            $rules['facility_scheme_id'] = ['nullable'];
        }

        if (Schema::hasColumn('internal_usages', 'outbound_number')) {
            $rules['outbound_number'] = ['nullable', 'string', 'max:100'];
        } else {
            $rules['outbound_number'] = ['nullable'];
        }

        if (Schema::hasColumn('internal_usages', 'sender_receiver_name')) {
            $rules['sender_receiver_name'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['sender_receiver_name'] = ['nullable'];
        }

        return $rules;
    }
}
