<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Regulatory\RegulatorySource;
use Illuminate\Http\Request;

class RegulatorySourceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $sources = RegulatorySource::query()
            ->when($q, fn ($x) => $x->where('source_name', 'like', "%$q%"))
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/RegulatorySources/Index', [
            'sources' => $sources,
            'filters' => ['q' => $q],
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/RegulatorySources/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['source_name' => ['required', 'string', 'max:255', 'unique:regulatory_sources,source_name']]);
        RegulatorySource::create($data);

        return to_route('apps.master-data.regulatory-sources.index');
    }

    public function edit(RegulatorySource $regulatorySource)
    {
        return inertia('Apps/MasterData/RegulatorySources/Edit', ['source' => $regulatorySource]);
    }

    public function update(Request $request, RegulatorySource $regulatorySource)
    {
        $data = $request->validate(['source_name' => ['required', 'string', 'max:255', 'unique:regulatory_sources,source_name,' . $regulatorySource->id]]);
        $regulatorySource->update($data);

        return to_route('apps.master-data.regulatory-sources.index');
    }

    public function destroy(string $id)
    {
        $ids = array_filter(explode(',', $id));
        RegulatorySource::whereIn('id', $ids)->delete();

        return back();
    }
}
