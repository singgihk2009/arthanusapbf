<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\DocumentAuditLog;

class DocumentAuditLogger
{
    public function log(Document $document, string $action, array $oldValues = [], array $newValues = []): void
    {
        DocumentAuditLog::query()->create([
            'business_id' => $document->business_id,
            'document_id' => $document->id,
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'performed_by' => auth()->id(),
            'performed_at' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
