<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Documents\DocumentVersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentCenterDocumentController extends Controller
{
    public function store(Request $request, DocumentVersioningService $svc): JsonResponse
    {
        $data = $this->validateData($request, true);
        $doc = $svc->createOriginalDocument($data, $request->file('file'));
        return response()->json($doc, 201);
    }

    public function revision(Request $request, Document $document, DocumentVersioningService $svc): JsonResponse
    {
        $data = $this->validateData($request, false);
        $doc = $svc->createRevision($document, array_merge($data, $document->only(['business_id','owner_type','owner_id','document_type_id'])), $request->file('file'));
        return response()->json($doc, 201);
    }

    public function renewal(Request $request, Document $document, DocumentVersioningService $svc): JsonResponse
    {
        $data = $this->validateData($request, false);
        $doc = $svc->createRenewal($document, array_merge($data, $document->only(['business_id','owner_type','owner_id','document_type_id'])), $request->file('file'));
        return response()->json($doc, 201);
    }

    public function versions(Document $document, DocumentVersioningService $svc): JsonResponse
    { return response()->json($svc->getVersionHistory($document)); }

    private function validateData(Request $request, bool $original): array
    {
        return $request->validate([
            'business_id' => [$original ? 'required' : 'nullable', 'integer'],
            'owner_type' => [$original ? 'required' : 'nullable', 'string'],
            'owner_id' => [$original ? 'required' : 'nullable', 'integer'],
            'document_type_id' => [$original ? 'required' : 'nullable', 'integer', 'exists:document_types,id'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'title' => ['nullable', 'string'],
            'document_number' => ['nullable', 'string'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
