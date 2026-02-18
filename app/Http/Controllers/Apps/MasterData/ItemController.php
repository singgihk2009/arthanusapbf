<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\ItemRequest;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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
        ]);
    }

    public function store(ItemRequest $request)
    {
        Item::query()->create($request->validated());

        return to_route('apps.master-data.items.index');
    }

    public function edit(Item $item)
    {
        return inertia('Apps/MasterData/Items/Edit', [
            'item' => $item,
            'categories' => Category::query()->select('id', 'name')->orderBy('name')->get(),
            'uoms' => Uom::query()->select('id', 'code', 'name')->orderBy('code')->get(),
        ]);
    }

    public function update(ItemRequest $request, Item $item)
    {
        $item->update($request->validated());

        return to_route('apps.master-data.items.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        Item::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
