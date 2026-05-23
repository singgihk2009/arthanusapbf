<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('price_list')?->id ?? $this->route('price_list');

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('price_lists', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', 'in:active,inactive'],
            'is_default' => ['boolean'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.uom_id' => ['nullable', 'integer'],
            'lines.*.min_qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_included' => ['boolean'],
            'lines.*.status' => ['required', 'in:active,inactive'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $seen = [];
            foreach ((array) $this->input('lines', []) as $idx => $line) {
                if (($line['status'] ?? 'active') !== 'active') {
                    continue;
                }
                $uomKey = $line['uom_id'] ?? 'null';
                $key = ($line['item_id'] ?? '').'|'.$uomKey.'|'.number_format((float) ($line['min_qty'] ?? 0), 4, '.', '');
                if (isset($seen[$key])) {
                    $validator->errors()->add("lines.$idx.min_qty", 'Duplicate active item/uom/min_qty line is not allowed.');
                }
                $seen[$key] = true;
            }
        });
    }
}
