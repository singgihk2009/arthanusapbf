<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemUomConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $conversionId = $this->route('conversion')?->id;

        return [
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'from_uom_id' => [
                'required',
                'integer',
                'exists:uoms,id',
                'different:to_uom_id',
                Rule::unique('item_uom_conversions', 'from_uom_id')
                    ->where(fn ($query) => $query
                        ->where('item_id', $this->input('item_id'))
                        ->where('to_uom_id', $this->input('to_uom_id')))
                    ->ignore($conversionId),
            ],
            'to_uom_id' => ['required', 'integer', 'exists:uoms,id', 'different:from_uom_id'],
            'factor' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_uom_id.unique' => 'Kombinasi item dan UOM sudah ada.',
        ];
    }
}
