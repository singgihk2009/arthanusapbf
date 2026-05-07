<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Inventory\FacilityScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class FacilitySchemeController extends Controller
{
    public function index(): Response
    {
        return inertia('Apps/MasterData/FacilitySchemes/Index', [
            'data' => FacilityScheme::query()->orderBy('code')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate(['code'=>'required|string|max:100|unique:facility_schemes,code','name'=>'required|string|max:255','requires_reference_no'=>'nullable|boolean']);
        FacilityScheme::create($payload + $request->only(['description','is_active','is_restricted','requires_tracking','requires_reporting','requires_approval','tax_treatment','ownership_type','allowed_movement_types','metadata']));
        return back()->with('success','Facility scheme created');
    }

    public function update(Request $request, FacilityScheme $facilityScheme): RedirectResponse
    {
        $payload = $request->validate(['code'=>'required|string|max:100|unique:facility_schemes,code,'.$facilityScheme->id,'name'=>'required|string|max:255','requires_reference_no'=>'nullable|boolean']);
        $facilityScheme->update($payload + $request->only(['description','is_active','is_restricted','requires_tracking','requires_reporting','requires_approval','tax_treatment','ownership_type','allowed_movement_types','metadata']));
        return back()->with('success','Facility scheme updated');
    }

    public function destroy(FacilityScheme $facilityScheme): RedirectResponse
    {
        $facilityScheme->delete();
        return back()->with('success','Facility scheme deleted');
    }
}
