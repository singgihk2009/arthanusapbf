<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentAuditLog;
use App\Models\DocumentRequirement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DocumentVersioningService
{
    public function createOriginalDocument(array $data, UploadedFile $file): Document
    {
        return DB::transaction(function () use ($data, $file) {
            $existing = $this->getCurrentDocument($data['owner_type'], (int) $data['owner_id'], (int) $data['document_type_id'], $data['business_id'] ?? null);
            if ($existing) {
                throw new \InvalidArgumentException('Document already exists. Please upload revision or renewal.');
            }

            $document = $this->createDocumentVersion(null, $data, $file, 'original');
            $this->log($document, 'original_uploaded', null, $document);

            return $document;
        });
    }

    public function createRevision(Document $oldDocument, array $data, UploadedFile $file): Document
    {
        if (! $oldDocument->canUploadRevision()) throw new \InvalidArgumentException('Only rejected document can upload revision.');
        return $this->replaceWithNewVersion($oldDocument, $data, $file, 'revision', 'revision_uploaded');
    }

    public function createRenewal(Document $oldDocument, array $data, UploadedFile $file): Document
    {
        if (! $oldDocument->canUploadRenewal()) throw new \InvalidArgumentException('Only expired or expiring-soon document can upload renewal.');
        return $this->replaceWithNewVersion($oldDocument, $data, $file, 'renewal', 'renewal_uploaded');
    }

    public function getCurrentDocument(string $ownerType, int $ownerId, int $documentTypeId, ?int $businessId): ?Document
    { return Document::query()->forOwner($ownerType, $ownerId)->where('document_type_id', $documentTypeId)->when($businessId, fn($q)=>$q->where('business_id',$businessId))->current()->latest('version_number')->first(); }
    public function getVersionHistory(Document $document){ return $document->versions()->orderBy('version_number')->get(); }

    public function ensureSingleCurrentVersion(string $ownerType, int $ownerId, int $documentTypeId, ?int $businessId): void
    {
        $docs = Document::query()->forOwner($ownerType,$ownerId)->where('document_type_id',$documentTypeId)->when($businessId, fn($q)=>$q->where('business_id',$businessId))->orderByDesc('version_number')->orderByDesc('id')->get();
        $keep = $docs->first();
        foreach ($docs as $doc) {
            $shouldCurrent = $keep && $doc->id === $keep->id;
            if ((bool)$doc->is_current !== $shouldCurrent) {
                $doc->update(['is_current' => $shouldCurrent]);
                $this->log($doc, 'current_version_changed', null, $doc);
            }
        }
    }

    private function replaceWithNewVersion(Document $oldDocument, array $data, UploadedFile $file, string $versionType, string $event): Document
    {
        return DB::transaction(function () use ($oldDocument, $data, $file, $versionType, $event) {
            $newDocument = $this->createDocumentVersion($oldDocument, $data, $file, $versionType);
            $oldDocument->update(['is_current' => false, 'replaced_by_document_id' => $newDocument->id]);
            $this->ensureSingleCurrentVersion($oldDocument->owner_type, $oldDocument->owner_id, $oldDocument->document_type_id, $oldDocument->business_id);
            $this->log($newDocument, $event, $oldDocument, $newDocument);
            return $newDocument;
        });
    }

    private function createDocumentVersion(?Document $base, array $data, UploadedFile $file, string $versionType): Document
    {
        $path = $file->store("private/document-center/{$data['owner_type']}/{$data['owner_id']}");
        $req = DocumentRequirement::query()->where('owner_type', $data['owner_type'])->where('document_type_id', $data['document_type_id'])->active()->first();
        $status = ($req?->requires_verification ?? true) ? 'pending_review' : 'verified';
        $rootId = $base?->getRootDocumentId();

        return Document::query()->create(array_merge(Arr::except($data, ['file']), [
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_path' => $path,
            'original_file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'parent_document_id' => $rootId,
            'version_number' => $base ? $base->getNextVersionNumber() : 1,
            'version_type' => $versionType,
            'is_current' => true,
            'status' => $status,
            'uploaded_by' => auth()->id(),
            'verified_by' => $status === 'verified' ? auth()->id() : null,
            'verified_at' => $status === 'verified' ? now() : null,
        ]));
    }

    private function log(Document $document, string $action, ?Document $old, Document $new): void
    { DocumentAuditLog::create(['document_id'=>$document->id,'business_id'=>$document->business_id,'action'=>$action,'old_values'=>$old?->only(['id','status','version_number']),'new_values'=>$new->only(['id','status','version_number','version_type']),'performed_by'=>auth()->id(),'performed_at'=>now()]); }
}
