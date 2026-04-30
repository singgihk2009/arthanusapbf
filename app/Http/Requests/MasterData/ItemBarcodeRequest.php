<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ItemBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $barcodeId = $this->route('barcode')?->id;

        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'barcode' => ['required', 'string', 'max:100', 'unique:item_barcodes,barcode,'. $barcodeId],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
