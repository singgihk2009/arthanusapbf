<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorRequest;
use App\Models\Procurement\Vendor;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index()
    {
        $vendors = Vendor::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('vendor_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Apps/Procurement/Vendors/Index', compact('vendors'));
    }

    public function create()
    {
        return Inertia::render('Apps/Procurement/Vendors/Form');
    }

    public function store(StoreVendorRequest $request)
    {
        Vendor::query()->create($request->validated());

        return to_route('apps.procurement.vendors.index');
    }

    public function edit(Vendor $vendor)
    {
        return Inertia::render('Apps/Procurement/Vendors/Form', compact('vendor'));
    }

    public function update(StoreVendorRequest $request, Vendor $vendor)
    {
        $vendor->update($request->validated());

        return to_route('apps.procurement.vendors.index');
    }

    public function destroy(string $id)
    {
        $ids = explode(',', $id);
        Vendor::query()->whereIn('id', $ids)->delete();

        return back();
    }
}
