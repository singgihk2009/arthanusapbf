<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FacilityScheme;
use Illuminate\Validation\ValidationException;

class FacilityReferenceValidationService
{
    public function validateFacilityReference(?int $facilitySchemeId, ?string $facilityReferenceNo): FacilityScheme
    {
        $facility = FacilityScheme::query()->find($facilitySchemeId);
        if (! $facility) {
            throw ValidationException::withMessages(['facility_scheme_id' => 'Facility scheme tidak ditemukan.']);
        }
        if (! $facility->is_active) {
            throw ValidationException::withMessages(['facility_scheme_id' => 'Facility scheme tidak aktif.']);
        }
        if ($facility->requires_reference_no && blank($facilityReferenceNo)) {
            throw ValidationException::withMessages(['facility_reference_no' => 'No referensi fasilitas wajib diisi.']);
        }

        return $facility;
    }
}
