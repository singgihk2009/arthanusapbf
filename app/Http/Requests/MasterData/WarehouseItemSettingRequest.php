<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseItemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $settingId = $this->route('min_stock')?->id;

        return [
            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                Rule::unique('warehouse_item_settings', 'warehouse_id')
                    ->where(fn ($query) => $query->where('item_id', $this->input('item_id')))
                    ->ignore($settingId),
            ],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'min_stock_base' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.unique' => 'Item untuk gudang ini sudah memiliki min stok.',
        ];
    }
}
