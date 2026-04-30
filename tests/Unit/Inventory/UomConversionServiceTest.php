<?php

use App\Services\Inventory\UomConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->uomStripId = DB::table('uoms')->insertGetId([
        'code' => 'STRIP',
        'name' => 'Strip',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->uomBoxId = DB::table('uoms')->insertGetId([
        'code' => 'BOX',
        'name' => 'Box',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->itemId = DB::table('items')->insertGetId([
        'sku' => 'ITEM-UOM-01',
        'name' => 'Vitamin C',
        'base_uom_id' => $this->uomBoxId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->service = app(UomConversionService::class);
});

it('converts quantity to base when direct conversion exists', function () {
    DB::table('item_uom_conversions')->insert([
        'item_id' => $this->itemId,
        'from_uom_id' => $this->uomStripId,
        'to_uom_id' => $this->uomBoxId,
        'factor' => 0.1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $qtyBase = $this->service->toBase($this->itemId, $this->uomStripId, 20);

    expect($qtyBase)->toBe(2.0);
});

it('converts quantity to base when only reverse conversion exists', function () {
    DB::table('item_uom_conversions')->insert([
        'item_id' => $this->itemId,
        'from_uom_id' => $this->uomBoxId,
        'to_uom_id' => $this->uomStripId,
        'factor' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $qtyBase = $this->service->toBase($this->itemId, $this->uomStripId, 20);

    expect($qtyBase)->toBe(2.0);
});

it('throws when no conversion path exists', function () {
    $this->service->toBase($this->itemId, $this->uomStripId, 1);
})->throws(InvalidArgumentException::class, 'UOM conversion to base is not configured for this item.');
