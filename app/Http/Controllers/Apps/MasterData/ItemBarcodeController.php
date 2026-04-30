<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemBarcodeRequest;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemBarcode;

class ItemBarcodeController extends Controller
{
    public function index()
    {
        $barcodes = ItemBarcode::query()
            ->with('item:id,sku,name')
            ->when(request()->search, fn ($query) => $query
                ->where('barcode', 'like', '%'.request()->search.'%')
                ->orWhereHas('item', fn ($itemQuery) => $itemQuery
                    ->where('name', 'like', '%'.request()->search.'%')
                    ->orWhere('sku', 'like', '%'.request()->search.'%')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Barcodes/Index', [
            'barcodes' => $barcodes,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Barcodes/Create', [
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(ItemBarcodeRequest $request)
    {
        ItemBarcode::query()->create($request->validated());

        return to_route('apps.master-data.barcodes.index');
    }

    public function edit(ItemBarcode $barcode)
    {
        return inertia('Apps/MasterData/Barcodes/Edit', [
            'barcode' => $barcode,
            'items' => Item::query()->select('id', 'sku', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(ItemBarcodeRequest $request, ItemBarcode $barcode)
    {
        $barcode->update($request->validated());

        return to_route('apps.master-data.barcodes.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        ItemBarcode::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
