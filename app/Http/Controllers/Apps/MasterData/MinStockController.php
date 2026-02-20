<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\WarehouseItemSettingRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItemSetting;

class MinStockController extends Controller
{
    public function index()
    {
        $minStocks = WarehouseItemSetting::query()
            ->with(['warehouse:id,code,name', 'item:id,sku,name'])
            ->when(request()->search, fn ($query) => $query
                ->whereHas('item', fn ($itemQuery) => $itemQuery
                    ->where('name', 'like', '%'.request()->search.'%')
                    ->orWhere('sku', 'like', '%'.request()->search.'%'))
                ->orWhereHas('warehouse', fn ($warehouseQuery) => $warehouseQuery
                    ->where('name', 'like', '%'.request()->search.'%')
                    ->orWhere('code', 'like', '%'.request()->search.'%')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/MinStocks/Index', [
            'minStocks' => $minStocks,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/MinStocks/Create', [
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(WarehouseItemSettingRequest $request)
    {
        WarehouseItemSetting::query()->create($request->validated());

        return to_route('apps.master-data.min-stocks.index');
    }

    public function edit(WarehouseItemSetting $min_stock)
    {
        return inertia('Apps/MasterData/MinStocks/Edit', [
            'minStock' => $min_stock,
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(WarehouseItemSettingRequest $request, WarehouseItemSetting $min_stock)
    {
        $min_stock->update($request->validated());

        return to_route('apps.master-data.min-stocks.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        WarehouseItemSetting::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
