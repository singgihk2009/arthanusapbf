<?php

namespace App\Http\Controllers\Apps\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Procurement\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegulatoryDocumentController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $documents = DocumentType::query()
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('category', 'like', "%{$q}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return inertia('Apps/MasterData/RegulatoryDocuments/Index', [
            'documents' => $documents,
            'filters' => ['q' => $q],
            'categories' => ['legal', 'regulatory', 'certification', 'tax', 'other'],
        ]);
    }

    public function create()
    {
        return inertia('Apps/MasterData/RegulatoryDocuments/Create', [
            'categories' => ['legal', 'regulatory', 'certification', 'tax', 'other'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);
        DocumentType::create($data);

        return to_route('apps.master-data.regulatory-documents.index');
    }

    public function edit(DocumentType $regulatoryDocument)
    {
        return inertia('Apps/MasterData/RegulatoryDocuments/Edit', [
            'document' => $regulatoryDocument,
            'categories' => ['legal', 'regulatory', 'certification', 'tax', 'other'],
        ]);
    }

    public function update(Request $request, DocumentType $regulatoryDocument)
    {
        $data = $this->validatePayload($request, $regulatoryDocument->id);
        $regulatoryDocument->update($data);

        return to_route('apps.master-data.regulatory-documents.index');
    }

    public function destroy(string $id)
    {
        $ids = array_filter(explode(',', $id));
        DocumentType::whereIn('id', $ids)->delete();

        return back();
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('document_types', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100', Rule::in(['legal', 'regulatory', 'certification', 'tax', 'other'])],
            'description' => ['nullable', 'string'],
            'is_required' => ['boolean'],
            'is_critical' => ['boolean'],
            'blocks_transaction' => ['boolean'],
            'requires_expiry_date' => ['boolean'],
            'default_validity_days' => ['nullable', 'integer', 'min:1'],
            'applicable_vendor_type' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }
}
