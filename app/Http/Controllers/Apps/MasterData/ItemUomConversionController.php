<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemUomConversionRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemUomConversion;
use App\Models\Inventory\Uom;

class ItemUomConversionController extends Controller
{
    public function index()
    {
        $conversions = ItemUomConversion::query()
            ->with(['item:id,sku,name', 'fromUom:id,code,name', 'toUom:id,code,name'])
            ->when(request()->search, fn ($query) => $query
                ->whereHas('item', fn ($itemQuery) => $itemQuery
                    ->where('name', 'like', '%'.request()->search.'%')
                    ->orWhere('sku', 'like', '%'.request()->search.'%')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Conversions/Index', [
            'conversions' => $conversions,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Conversions/Create', [
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
        ]);
    }

    public function store(ItemUomConversionRequest $request)
    {
        ItemUomConversion::query()->create($request->validated());

        return to_route('apps.master-data.conversions.index');
    }

    public function edit(ItemUomConversion $conversion)
    {
        return inertia('Apps/MasterData/Conversions/Edit', [
            'conversion' => $conversion,
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
        ]);
    }

    public function update(ItemUomConversionRequest $request, ItemUomConversion $conversion)
    {
        $conversion->update($request->validated());

        return to_route('apps.master-data.conversions.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        ItemUomConversion::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
