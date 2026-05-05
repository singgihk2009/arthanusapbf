<?php

use App\Models\CompanyModule;
use App\Models\Inventory\StockMovement;
use App\Models\Procurement\PurchaseOrderItem;
use App\Services\CompanyModuleService;
use App\Services\Procurement\FacilityInheritanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can enable and check module', function () {
    CompanyModule::query()->create([
        'company_id' => 1,
        'module_code' => 'kek_compliance',
        'is_enabled' => true,
        'settings_json' => ['strict_mode' => true],
    ]);

    $service = app(CompanyModuleService::class);
    expect($service->isEnabled(1, 'kek_compliance'))->toBeTrue();
    expect($service->getSettings(1, 'kek_compliance'))->toBe(['strict_mode' => true]);
});

it('inherits facility tagging from po line into gr payload map', function () {
    $poLine = new PurchaseOrderItem([
        'is_facility_item' => true,
        'facility_type' => 'tax_exemption',
        'facility_document_id' => 10,
        'facility_reference_no' => 'KEK-123',
        'kek_classification' => 'machine',
    ]);

    $mapped = app(FacilityInheritanceService::class)->mapFromPoLine($poLine);
    expect($mapped['is_facility_item'])->toBeTrue()
        ->and($mapped['facility_type'])->toBe('tax_exemption')
        ->and($mapped['facility_reference_no'])->toBe('KEK-123');
});

it('keeps facility fields on stock movement', function () {
    $movement = new StockMovement([
        'is_facility_item' => true,
        'facility_type' => 'import_facility',
        'kek_classification' => 'raw_material',
        'facility_status' => 'active',
    ]);

    expect($movement->is_facility_item)->toBeTrue()
        ->and($movement->facility_type)->toBe('import_facility')
        ->and($movement->kek_classification)->toBe('raw_material');
});
