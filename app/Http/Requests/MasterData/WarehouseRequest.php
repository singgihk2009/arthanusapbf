<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:warehouses,code,'. $warehouseId],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
