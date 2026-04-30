<?php

namespace App\Http\Requests\Inventory;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceivingEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('lines', []))
            ->map(function ($line): array {
                if (! is_array($line)) {
                    return [];
                }

                if (array_key_exists('expired_date', $line)) {
                    $line['expired_date'] = $this->normalizeDateValue($line['expired_date']);
                }

                foreach (['qty', 'price'] as $numericField) {
                    if (array_key_exists($numericField, $line)) {
                        $line[$numericField] = $this->normalizeNumericValue($line[$numericField]);
                    }
                }

                return $line;
            })
            ->all();

        $this->merge([
            'transaction_date' => $this->normalizeDateValue($this->input('transaction_date')),
            'lines' => $lines,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'transaction_date' => ['required', 'date'],
            'transaction_code' => ['required', Rule::in(['PEMBELIAN', 'RETUR', 'ADJUSTMENT'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_number' => ['nullable', 'string', 'max:100'],
            'lines.*.expired_date' => ['nullable', 'date'],
            'lines.*.notes' => ['nullable', 'string'],
        ];
    }

    private function normalizeDateValue(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $value = trim($value);
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return $value;
    }

    private function normalizeNumericValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        // Support both "1,500.75" and "1.500,75" input styles.
        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                return str_replace(',', '.', str_replace('.', '', $value));
            }

            return str_replace(',', '', $value);
        }

        return str_contains($value, ',')
            ? str_replace(',', '.', $value)
            : $value;
    }
}
