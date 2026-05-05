<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use Illuminate\Support\Collection;

class DocumentRequirementService
{
    private const VALID_STATUSES = ['verified'];

    public function getRequirementsForOwnerType(string $ownerType, ?int $businessId = null): Collection
    {
        return DocumentRequirement::query()
            ->with('documentType')
            ->forOwnerType($ownerType)
            ->forBusiness($businessId)
            ->active()
            ->orderBy('sort_order')
            ->get();
    }

    public function getRequiredTypesForOwnerType(string $ownerType, ?int $businessId = null): Collection
    {
        return $this->getRequirementsForOwnerType($ownerType, $businessId)
            ->where('is_required', true)
            ->values();
    }

    public function getMissingRequiredDocuments(string $ownerType, int $ownerId, ?int $businessId = null): Collection
    {
        return $this->getRequiredTypesForOwnerType($ownerType, $businessId)
            ->filter(fn (DocumentRequirement $requirement) => !$this->hasCompliantDocument($ownerType, $ownerId, $requirement))
            ->values();
    }

    public function getDocumentCompletionStatus(string $ownerType, int $ownerId, ?int $businessId = null): array
    {
        $required = $this->getRequiredTypesForOwnerType($ownerType, $businessId);
        $missing = $this->getMissingRequiredDocuments($ownerType, $ownerId, $businessId);

        $total = $required->count();
        $uploaded = max(0, $total - $missing->count());

        return [
            'total_required' => $total,
            'uploaded_required' => $uploaded,
            'missing_required' => $missing->count(),
            'completion_percentage' => $total === 0 ? 100 : (int) round(($uploaded / $total) * 100),
            'missing_documents' => $missing,
            'requirements_with_status' => $required->map(function (DocumentRequirement $requirement) use ($ownerType, $ownerId) {
                return [
                    'requirement' => $requirement,
                    'is_uploaded' => $this->hasCompliantDocument($ownerType, $ownerId, $requirement),
                ];
            })->values(),
        ];
    }

    public function getExpiringDocuments(?string $ownerType = null, int $days = 30): Collection
    {
        $today = now()->startOfDay();

        return Document::query()
            ->with('documentType')
            ->when($ownerType, fn ($q) => $q->where('owner_type', $ownerType))
            ->where('status', 'verified')
            ->whereNotNull('expiry_date')
            ->get()
            ->filter(function (Document $document) use ($today, $days) {
                $requirement = DocumentRequirement::query()
                    ->where('owner_type', $document->owner_type)
                    ->where('document_type_id', $document->document_type_id)
                    ->active()
                    ->first();

                $reminderWindow = $requirement?->reminder_days_before_expiry ?? $days;
                $threshold = $today->copy()->addDays($reminderWindow);
                $expiry = \Illuminate\Support\Carbon::parse($document->expiry_date)->endOfDay();

                return $expiry->between($today, $threshold);
            })
            ->values();
    }

    public function getUploadableTypesForOwnerType(string $ownerType, ?int $businessId = null): Collection
    {
        $requirements = $this->getRequirementsForOwnerType($ownerType, $businessId);
        if ($requirements->isNotEmpty()) {
            return $requirements->where('is_active', true)->pluck('documentType')->filter()->values();
        }

        return DocumentType::query()
            ->where(function ($q) use ($ownerType) {
                $q->whereNull('applicable_owner_types')
                    ->orWhereJsonContains('applicable_owner_types', $ownerType);
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    private function hasCompliantDocument(string $ownerType, int $ownerId, DocumentRequirement $requirement): bool
    {
        $query = Document::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('document_type_id', $requirement->document_type_id)
            ->where('is_current', true)->where('status', 'verified');

        if ($requirement->is_expirable) {
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', now()->toDateString());
        }

        return $query->exists();
    }
}
