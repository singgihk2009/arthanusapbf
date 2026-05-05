<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use App\Models\DocumentType;
use App\Services\DocumentRequirementService;
use App\Services\Documents\DocumentOwnerResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentRequirementController extends Controller
{
    public function setupPage(DocumentOwnerResolver $resolver): Response
    {
        return Inertia::render('Apps/DocumentCenter/RequirementSetup', [
            'ownerTypes' => $resolver->allowedOwnerTypes(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = DocumentRequirement::query()->with('documentType');

        foreach (['owner_type', 'document_type_id', 'is_required', 'is_active'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('category')) {
            $query->whereHas('documentType', fn ($q) => $q->where('category', $request->string('category')));
        }

        return response()->json($query->paginate(30));
    }

    public function store(Request $request): JsonResponse
    {
        $requirement = DocumentRequirement::create($this->validatePayload($request));
        return response()->json($requirement->load('documentType'));
    }

    public function update(Request $request, DocumentRequirement $requirement): JsonResponse
    {
        $requirement->update($this->validatePayload($request));
        return response()->json($requirement->fresh('documentType'));
    }

    public function destroy(DocumentRequirement $requirement): JsonResponse
    {
        $requirement->delete();
        return response()->json(['ok' => true]);
    }

    public function bulkSave(Request $request): JsonResponse
    {
        $ownerTypes = array_keys(config('document_owners', []));
        $data = $request->validate([
            'owner_type' => ['required', 'string', Rule::in($ownerTypes)],
            'requirements' => ['required', 'array'],
            'requirements.*.document_type_id' => ['required', 'exists:document_types,id'],
            'requirements.*.is_active' => ['nullable', 'boolean'],
            'requirements.*.is_required' => ['nullable', 'boolean'],
            'requirements.*.is_expirable' => ['nullable', 'boolean'],
            'requirements.*.requires_verification' => ['nullable', 'boolean'],
            'requirements.*.reminder_days_before_expiry' => ['nullable', 'integer', 'min:0'],
            'requirements.*.sort_order' => ['nullable', 'integer'],
            'requirements.*.notes' => ['nullable', 'string'],
        ]);

        $businessId = auth()->user()->business_id ?? null;
        foreach ($data['requirements'] as $item) {
            DocumentRequirement::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'owner_type' => $data['owner_type'],
                    'document_type_id' => $item['document_type_id'],
                ],
                [
                    'is_active' => (bool) ($item['is_active'] ?? false),
                    'is_required' => (bool) ($item['is_required'] ?? false),
                    'is_expirable' => (bool) ($item['is_expirable'] ?? false),
                    'requires_verification' => (bool) ($item['requires_verification'] ?? false),
                    'reminder_days_before_expiry' => $item['reminder_days_before_expiry'] ?? 30,
                    'sort_order' => $item['sort_order'] ?? 0,
                    'notes' => $item['notes'] ?? null,
                ]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function ownerTypes(DocumentOwnerResolver $resolver): JsonResponse
    {
        return response()->json($resolver->allowedOwnerTypes());
    }

    public function matrix(string $ownerType): JsonResponse
    {
        abort_unless(in_array($ownerType, array_keys(config('document_owners', [])), true), 422, 'Invalid owner type');

        $businessId = auth()->user()->business_id ?? null;
        $requirements = DocumentRequirement::query()
            ->forBusiness($businessId)
            ->forOwnerType($ownerType)
            ->get()
            ->keyBy('document_type_id');

        $rows = DocumentType::query()
            ->orderBy('sort_order')
            ->get()
            ->map(function (DocumentType $type) use ($requirements) {
                $requirement = $requirements->get($type->id);

                return [
                    'document_type_id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'category' => $type->category,
                    'is_active' => (bool) ($requirement->is_active ?? false),
                    'is_required' => (bool) ($requirement->is_required ?? false),
                    'is_expirable' => (bool) ($requirement->is_expirable ?? $type->is_expirable),
                    'requires_verification' => (bool) ($requirement->requires_verification ?? $type->requires_verification),
                    'reminder_days_before_expiry' => $requirement->reminder_days_before_expiry ?? 30,
                    'sort_order' => $requirement->sort_order ?? ($type->sort_order ?? 0),
                    'notes' => $requirement->notes,
                ];
            });

        return response()->json($rows);
    }

    public function completion(string $ownerType, int $ownerId, DocumentRequirementService $service): JsonResponse
    {
        abort_unless(in_array($ownerType, array_keys(config('document_owners', [])), true), 422, 'Invalid owner type');

        return response()->json(
            $service->getDocumentCompletionStatus($ownerType, $ownerId, auth()->user()->business_id ?? null)
        );
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'business_id' => ['nullable', 'integer'],
            'owner_type' => ['required', 'string', Rule::in(array_keys(config('document_owners', [])))],
            'document_type_id' => ['required', 'exists:document_types,id'],
            'is_active' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'is_expirable' => ['nullable', 'boolean'],
            'requires_verification' => ['nullable', 'boolean'],
            'reminder_days_before_expiry' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
