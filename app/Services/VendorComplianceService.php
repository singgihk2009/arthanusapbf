<?php

namespace App\Services;

use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorDocumentRequirement;

class VendorComplianceService
{
    public function evaluate(Vendor $vendor): array { return $this->generateComplianceSummary($vendor); }

    public function getRequiredDocuments(Vendor $vendor)
    {
        return VendorDocumentRequirement::with('documentType')->where('is_active', true)
            ->where(function ($q) use ($vendor) { $q->whereNull('vendor_type')->orWhere('vendor_type', $vendor->vendor_type); })
            ->get();
    }

    public function getMissingDocuments(Vendor $vendor): array
    {
        $missing = [];
        $docs = $vendor->documents()->get()->keyBy('document_type_id');
        foreach ($this->getRequiredDocuments($vendor) as $req) {
            if (!$req->is_required) continue;
            $doc = $docs->get($req->document_type_id);
            if (!$doc || ($req->requires_expiry_date && !$doc->expiry_date)) $missing[] = $req;
        }
        return $missing;
    }

    public function getExpiredDocuments(Vendor $vendor): array
    {
        $expired = [];
        $docs = $vendor->documents()->get()->keyBy('document_type_id');
        foreach ($this->getRequiredDocuments($vendor) as $req) {
            $doc = $docs->get($req->document_type_id);
            if ($doc && $req->requires_expiry_date && $doc->expiry_date && $doc->expiry_date->isPast()) $expired[] = $req;
        }
        return $expired;
    }

    public function getExpiringSoonDocuments(Vendor $vendor, int $days = 30): array
    {
        $soon=[]; $docs=$vendor->documents()->get()->keyBy('document_type_id');
        foreach ($this->getRequiredDocuments($vendor) as $req) {
            $doc = $docs->get($req->document_type_id); if(!$doc || !$doc->expiry_date) continue;
            if ($doc->expiry_date->isFuture() && $doc->expiry_date->lte(now()->addDays($req->warning_days_before_expiry ?: $days))) $soon[]=$req;
        }
        return $soon;
    }

    public function hasBlockingIssue(Vendor $vendor): bool { return count($this->generateComplianceSummary($vendor)['blocking_reasons'])>0; }
    public function canTransact(Vendor $vendor): bool { return !$this->hasBlockingIssue($vendor); }

    public function generateComplianceSummary(Vendor $vendor): array
    {
        $missing=$this->getMissingDocuments($vendor); $expired=$this->getExpiredDocuments($vendor); $soon=$this->getExpiringSoonDocuments($vendor);
        $blocking=[];
        foreach (array_merge($missing,$expired) as $req) if ($req->blocks_transaction || $req->is_critical) $blocking[] = ($req->documentType->name ?? $req->documentType->code ?? 'Document').' bermasalah';
        $status='compliant';
        if ($blocking) $status='blocked'; elseif ($expired) $status='expired'; elseif ($soon) $status='expiring_soon'; elseif ($missing) $status='warning';
        return ['compliance_status'=>$status,'missing_documents'=>$missing,'expired_documents'=>$expired,'expiring_soon_documents'=>$soon,'blocking_reasons'=>array_values(array_unique($blocking)),'can_create_po'=>empty($blocking)];
    }
}
