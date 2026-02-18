<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('item')?->id;

        return [
            'sku' => ['required', 'string', 'max:100', 'unique:items,sku,'. $itemId],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'base_uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'default_barcode' => ['nullable', 'string', 'max:100'],
            'track_expired' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
