<?php

namespace App\Http\Requests\MasterData;

use App\Services\Inventory\ItemPictureService;
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
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'min_stock_base' => ['nullable', 'numeric', 'min:0'],
            'track_expired' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'pictures' => ['nullable', 'array', 'max:'.ItemPictureService::MAX_PICTURES_PER_ITEM],
            'pictures.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'default_new_picture_index' => ['nullable', 'integer', 'min:0'],
            'default_picture_id' => ['nullable', 'integer', 'exists:item_pictures,id'],
        ];
    }
}
