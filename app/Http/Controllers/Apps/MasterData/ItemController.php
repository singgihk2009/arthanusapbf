<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemRequest;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemBarcode;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItemSetting;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-item-data', only: ['index']),
            new Middleware('permission:master-item-create', only: ['create', 'store']),
            new Middleware('permission:master-item-update', only: ['edit', 'update']),
            new Middleware('permission:master-item-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $items = Item::query()
            ->with(['baseUom:id,code,name', 'category:id,name'])
            ->withSum('warehouseItemSettings as minimum_stock_base', 'min_stock_base')
            ->when(request()->search, fn ($query) => $query->where('name', 'like', '%'.request()->search.'%')->orWhere('sku', 'like', '%'.request()->search.'%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Items/Index', [
            'items' => $items,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Items/Create', [
            'categories' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(ItemRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $item = Item::query()->create(collect($validated)->except(['warehouse_id', 'min_stock_base'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
        });

        return to_route('apps.master-data.items.index');
    }

    public function edit(Item $item)
    {
        $item->load(['warehouseItemSettings' => fn ($query) => $query->latest()->limit(1)]);

        return inertia('Apps/MasterData/Items/Edit', [
            'item' => $item,
            'categories' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
            'warehouses' => Warehouse::query()->select('id', 'code', 'name')->orderBy('name')->get(),
            'minimumStockSetting' => $item->warehouseItemSettings->first(),
        ]);
    }

    public function update(ItemRequest $request, Item $item)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($item, $validated) {
            $item->update(collect($validated)->except(['warehouse_id', 'min_stock_base'])->all());

            $this->syncDefaultBarcode($item, $validated['default_barcode'] ?? null);
            $this->syncMinimumStock($item, $validated['warehouse_id'] ?? null, $validated['min_stock_base'] ?? null);
        });

        return to_route('apps.master-data.items.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        Item::query()->whereIn('id', $ids)->delete();

        return back();
    }

    private function syncDefaultBarcode(Item $item, ?string $barcode): void
    {
        if (! $barcode) {
            return;
        }

        ItemBarcode::query()->updateOrCreate(
            ['barcode' => $barcode],
            ['item_id' => $item->id, 'note' => 'Default barcode dari master item']
        );
    }

    private function syncMinimumStock(Item $item, mixed $warehouseId, mixed $minimumStock): void
    {
        if (! $warehouseId || $minimumStock === null || $minimumStock === '') {
            return;
        }

        WarehouseItemSetting::query()->updateOrCreate(
            [
                'warehouse_id' => $warehouseId,
                'item_id' => $item->id,
            ],
            [
                'min_stock_base' => $minimumStock,
            ]
        );
    }
}
