<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Services\DocumentRequirementService;
use App\Services\Documents\DocumentOwnerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentMonitoringController extends Controller
{
    public function expiryPage(): Response
    {
        return Inertia::render('Apps/DocumentCenter/ExpiryMonitoring');
    }

    public function missingPage(): Response
    {
        return Inertia::render('Apps/DocumentCenter/MissingRequiredDocuments');
    }

    public function expiringSoon(Request $request, DocumentRequirementService $service): JsonResponse
    {
        $days = (int) ($request->integer('days') ?: 30);
        $ownerType = $request->string('owner_type')->toString() ?: null;
        return response()->json($service->getExpiringDocuments($ownerType, $days));
    }

    public function missingRequired(Request $request, DocumentRequirementService $service, DocumentOwnerResolver $resolver): JsonResponse
    {
        $ownerTypes = $request->filled('owner_type') ? [$request->string('owner_type')->toString()] : array_intersect($resolver->allowedOwnerTypes(), ['vendor', 'company']);
        $businessId = auth()->user()->business_id ?? null;
        $result = [];

        foreach ($ownerTypes as $ownerType) {
            $cfg = config("document_owners.$ownerType");
            if (!$cfg || !class_exists($cfg['model'])) {
                continue;
            }

            $owners = $cfg['model']::query()->limit(200)->get();
            foreach ($owners as $owner) {
                $missing = $service->getMissingRequiredDocuments($ownerType, $owner->id, $businessId);
                if ($missing->isEmpty()) {
                    continue;
                }

                $completion = $service->getDocumentCompletionStatus($ownerType, $owner->id, $businessId);
                $result[] = [
                    'owner_type' => $ownerType,
                    'owner_id' => $owner->id,
                    'owner_name' => $owner->{$cfg['name_column']} ?? (string) $owner->id,
                    'missing_document_types' => $missing->map(fn ($item) => $item->documentType?->name ?? $item->documentType?->code)->values(),
                    'completion_percentage' => $completion['completion_percentage'],
                ];
            }
        }

        return response()->json($result);
    }
}
