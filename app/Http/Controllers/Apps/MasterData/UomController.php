<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\UomRequest;
use App\Models\Inventory\Uom;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UomController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:master-uom-data', only: ['index']),
            new Middleware('permission:master-uom-create', only: ['create', 'store']),
            new Middleware('permission:master-uom-update', only: ['edit', 'update']),
            new Middleware('permission:master-uom-delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        $uoms = Uom::query()
            ->when(request()->search, fn ($query) => $query->where('name', 'like', '%'.request()->search.'%')->orWhere('code', 'like', '%'.request()->search.'%'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/Uoms/Index', [
            'uoms' => $uoms,
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/Uoms/Create');
    }

    public function store(UomRequest $request)
    {
        Uom::query()->create($request->validated());

        return to_route('apps.master-data.uoms.index');
    }

    public function edit(Uom $uom)
    {
        return inertia('Apps/MasterData/Uoms/Edit', [
            'uom' => $uom,
        ]);
    }

    public function update(UomRequest $request, Uom $uom)
    {
        $uom->update($request->validated());

        return to_route('apps.master-data.uoms.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);

        Uom::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
