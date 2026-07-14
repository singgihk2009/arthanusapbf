<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\PartyType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PartyTypeController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->string('category')->toString();

        return Inertia::render('Apps/MasterData/PartyTypes/Index', [
            'partyTypes' => PartyType::query()
                ->when($category !== '', fn ($query) => $query->where('category', $category))
                ->orderBy('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['category' => $category],
        ]);
    }

    public function store(Request $request)
    {
        PartyType::create($this->validated($request));

        return back()->with('success', 'Type berhasil ditambahkan.');
    }

    public function update(Request $request, PartyType $partyType)
    {
        $partyType->update($this->validated($request, $partyType));

        return back()->with('success', 'Type berhasil diperbarui.');
    }

    public function destroy(PartyType $partyType)
    {
        $partyType->delete();

        return back()->with('success', 'Type berhasil dihapus.');
    }

    private function validated(Request $request, ?PartyType $partyType = null): array
    {
        return $request->validate([
            'category' => ['required', Rule::in(['vendor', 'customer'])],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('party_types', 'code')->where('category', $request->input('category'))->ignore($partyType?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'prefix' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('party_types', 'prefix')->where('category', $request->input('category'))->ignore($partyType?->id),
            ],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
