<?php

use App\Models\Procurement\PurchaseOrder;

test('psychotropic is an available purchase order type', function () {
    expect(PurchaseOrder::TYPES)->toContain('psychotropic')
        ->and(PurchaseOrder::TYPE_LABELS['psychotropic'])->toBe('PO Psikotropika');
});
