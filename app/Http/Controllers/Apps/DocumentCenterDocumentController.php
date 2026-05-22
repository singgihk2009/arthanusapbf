<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Documents\DocumentAuditLogger;
use App\Services\Documents\DocumentVersioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function show(Document $document): JsonResponse
    {
        $this->authorizeDocumentAction($document, 'document.view');
        return response()->json($this->withRelations($document));
    }

    public function download(Document $document, DocumentAuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeDocumentAction($document, 'document.download');
        $auditLogger->log($document, 'document_downloaded');
        return Storage::disk($document->storage_disk)->download($document->file_path, $document->original_file_name ?: basename($document->file_path));
    }

    public function verify(Document $document, DocumentAuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeDocumentAction($document, 'document.verify');
        abort_unless($document->status === 'pending_review', 422, 'Only pending review documents can be verified.');

        $old = ['status' => $document->status];
        $document->fill([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'rejected_reason' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ])->save();

        $auditLogger->log($document, 'document_verified', $old, $document->only(['status', 'verified_by', 'verified_at']));

        return response()->json(['success' => true, 'message' => 'Document verified successfully', 'document' => $this->withRelations($document)]);
    }

    public function reject(Request $request, Document $document, DocumentAuditLogger $auditLogger): JsonResponse
    {
        $this->authorizeDocumentAction($document, 'document.reject');
        abort_unless($document->status === 'pending_review', 422, 'Only pending review documents can be rejected.');

        $payload = $request->validate(['rejected_reason' => ['required', 'string', 'min:5']]);
        $old = ['status' => $document->status];

        $document->fill([
            'status' => 'rejected',
            'rejected_reason' => $payload['rejected_reason'],
            'verified_by' => null,
            'verified_at' => null,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ])->save();

        $auditLogger->log($document, 'document_rejected', $old, $document->only(['status', 'rejected_reason', 'rejected_by', 'rejected_at']));

        return response()->json(['success' => true, 'message' => 'Document rejected successfully', 'document' => $this->withRelations($document)]);
    }

    public function auditLogs(Document $document): JsonResponse
    {
        $this->authorizeDocumentAction($document, 'document.audit.view');
        return response()->json($document->auditLogs()->latest('performed_at')->get());
    }

    public function destroy(Document $document, DocumentAuditLogger $auditLogger): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $canDelete = $user->can('document.delete')
            || $user->hasAnyRole(['super-admin', 'admin'])
            || ($document->owner_type === 'receiving_entry' && $user->can('inventory.receiving.update'));
        abort_unless($canDelete, 403, 'You are not allowed to remove this document.');

        if ($document->business_id !== null && (int) $document->business_id !== (int) ($user->business_id ?? $document->business_id)) {
            abort(403, 'Document business does not match.');
        }

        $old = $document->only(['id', 'status', 'file_path', 'storage_disk', 'owner_type', 'owner_id']);

        if ($document->file_path && Storage::disk($document->storage_disk)->exists($document->file_path)) {
            Storage::disk($document->storage_disk)->delete($document->file_path);
        }

        $document->delete();
        $auditLogger->log($document, 'document_deleted', $old, []);

        return response()->json(['success' => true, 'message' => 'Document removed successfully.']);
    }

    public function pendingReviewPage()
    {
        return Inertia::render('Apps/DocumentCenter/PendingReview');
    }

    public function pendingReviewList(): JsonResponse
    {
        $docs = Document::query()->with(['type', 'uploadedBy', 'verifiedBy', 'rejectedBy'])
            ->where('status', 'pending_review')
            ->latest('created_at')
            ->get();
        return response()->json($docs);
    }

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

    private function authorizeDocumentAction(Document $document, string $permission): void
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $allowedRole = $user->hasAnyRole(['super-admin', 'admin'])
            || ($user->hasAnyRole(['compliance', 'manager']) && in_array($permission, ['document.verify', 'document.reject'], true));

        abort_unless($user->can($permission) || $allowedRole, 403);
        if ($document->business_id !== null && (int) $document->business_id !== (int) ($user->business_id ?? $document->business_id)) {
            abort(403, 'Document business does not match.');
        }
    }

    private function withRelations(Document $document): Document
    {
        return $document->load(['type', 'uploadedBy', 'verifiedBy', 'rejectedBy', 'auditLogs']);
    }
}
