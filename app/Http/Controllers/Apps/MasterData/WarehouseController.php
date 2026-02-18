<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\WarehouseRequest;
use App\Models\Inventory\Warehouse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-warehouse-data', only: ['index']),
            new Middleware('permission:master-warehouse-create', only: ['create', 'store']),
            new Middleware('permission:master-warehouse-update', only: ['edit', 'update']),
            new Middleware('permission:master-warehouse-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $warehouses = Warehouse::query()
            ->when(request()->search, fn ($query) => $query->where('name', 'like', '%'.request()->search.'%')->orWhere('code', 'like', '%'.request()->search.'%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Warehouses/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Warehouses/Create');
    }

    public function store(WarehouseRequest $request)
    {
        Warehouse::query()->create($request->validated());

        return to_route('apps.master-data.warehouses.index');
    }

    public function edit(Warehouse $warehouse)
    {
        return inertia('Apps/MasterData/Warehouses/Edit', [
            'warehouse' => $warehouse,
        ]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse->update($request->validated());

        return to_route('apps.master-data.warehouses.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        Warehouse::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
