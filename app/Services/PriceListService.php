<?php

namespace App\Services;

use App\Models\Inventory\Item;
use App\Models\Sales\PriceList;
use App\Models\Sales\PriceListLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    public function generateCode(): string
    {
        $last = PriceList::withTrashed()->orderByDesc('id')->value('code');
        $lastNumber = $last && preg_match('/PL-(\d+)/', $last, $m) ? (int) $m[1] : 0;

        do {
            $lastNumber++;
            $code = 'PL-'.str_pad((string) $lastNumber, 6, '0', STR_PAD_LEFT);
        } while (PriceList::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function savePriceList(array $data): PriceList
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = $data['code'] ?: $this->generateCode();
            $lines = Arr::pull($data, 'lines', []);

            if (($data['is_default'] ?? false) && ($data['status'] ?? 'active') === 'active') {
                PriceList::where('is_default', true)->update(['is_default' => false]);
            }

            $priceList = PriceList::create($data);
            foreach ($lines as $line) {
                $priceList->lines()->create($this->normalizeLine($line));
            }

            return $priceList->load('lines');
        });
    }

    public function updatePriceList(PriceList $priceList, array $data): PriceList
    {
        return DB::transaction(function () use ($priceList, $data) {
            $lines = Arr::pull($data, 'lines', []);
            if (empty($data['code'])) {
                $data['code'] = $priceList->code ?: $this->generateCode();
            }

            if (($data['is_default'] ?? false) && ($data['status'] ?? 'active') === 'active') {
                PriceList::where('id', '!=', $priceList->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $priceList->update($data);

            $existingIds = $priceList->lines()->pluck('id')->all();
            $keptIds = [];
            foreach ($lines as $line) {
                $payload = $this->normalizeLine($line);
                if (!empty($line['id'])) {
                    $model = $priceList->lines()->whereKey($line['id'])->first();
                    if ($model) {
                        $model->update($payload);
                        $keptIds[] = $model->id;
                    }
                    continue;
                }
                $new = $priceList->lines()->create($payload);
                $keptIds[] = $new->id;
            }
            $deleteIds = array_diff($existingIds, $keptIds);
            if ($deleteIds) {
                $priceList->lines()->whereIn('id', $deleteIds)->delete();
            }

            return $priceList->load('lines');
        });
    }

    public function resolvePrice($itemId, $qty = 1, $uomId = null, $date = null, $priceListId = null): array
    {
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $qty = (float) $qty;

        $priceListQuery = PriceList::query()->where('status', 'active')
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            });

        $priceList = $priceListId ? $priceListQuery->whereKey($priceListId)->first() : $priceListQuery->where('is_default', true)->latest('id')->first();

        if ($priceList) {
            $line = PriceListLine::query()
                ->where('price_list_id', $priceList->id)
                ->where('item_id', $itemId)
                ->where('status', 'active')
                ->when($uomId, fn ($q) => $q->where(function ($sq) use ($uomId) {
                    $sq->where('uom_id', $uomId)->orWhereNull('uom_id');
                }), fn ($q) => $q->whereNull('uom_id'))
                ->where('min_qty', '<=', $qty)
                ->orderByDesc('min_qty')
                ->orderByDesc('id')
                ->first();

            if ($line) {
                return [
                    'price_list_id' => $priceList->id,
                    'price_list_line_id' => $line->id,
                    'unit_price' => (float) $line->price,
                    'discount_percent' => (float) $line->discount_percent,
                    'tax_included' => (bool) $line->tax_included,
                    'source' => 'price_list',
                ];
            }
        }

        $item = Item::find($itemId);
        $fallbackField = collect(['selling_price', 'sale_price', 'price', 'default_price'])->first(fn ($field) => $item && isset($item->{$field}));
        $fallbackPrice = $fallbackField ? (float) ($item->{$fallbackField} ?? 0) : 0;

        return [
            'price_list_id' => $priceList?->id,
            'price_list_line_id' => null,
            'unit_price' => $fallbackPrice,
            'discount_percent' => 0,
            'tax_included' => false,
            'source' => $fallbackPrice > 0 ? 'item_default' : 'none',
        ];
    }

    protected function normalizeLine(array $line): array
    {
        return [
            'item_id' => $line['item_id'],
            'uom_id' => $line['uom_id'] ?? null,
            'min_qty' => $line['min_qty'],
            'price' => $line['price'],
            'discount_percent' => $line['discount_percent'] ?? 0,
            'tax_included' => (bool) ($line['tax_included'] ?? false),
            'status' => $line['status'] ?? 'active',
        ];
    }
}
