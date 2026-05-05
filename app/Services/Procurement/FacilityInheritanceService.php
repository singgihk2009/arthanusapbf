<?php

namespace App\Services\Procurement;

use App\Models\Procurement\PurchaseOrderItem;

class FacilityInheritanceService
{
    public function mapFromPoLine(PurchaseOrderItem $purchaseOrderItem): array
    {
        return [
            'is_facility_item' => (bool) $purchaseOrderItem->is_facility_item,
            'facility_type' => $purchaseOrderItem->facility_type,
            'facility_document_id' => $purchaseOrderItem->facility_document_id,
            'facility_reference_no' => $purchaseOrderItem->facility_reference_no,
            'kek_classification' => $purchaseOrderItem->kek_classification,
        ];
    }
}
