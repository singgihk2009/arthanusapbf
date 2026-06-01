<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('converts usage unit price and valuation based on selected uom', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('inventory-reports-access', 'web');
    $user->givePermissionTo($permission);
    actingAs($user);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-01',
        'name' => 'Warehouse 01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $stripUomId = DB::table('uoms')->insertGetId([
        'code' => 'STRIP',
        'name' => 'Strip',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $boxUomId = DB::table('uoms')->insertGetId([
        'code' => 'BOX',
        'name' => 'Box',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('items')->insertGetId([
        'sku' => 'P003',
        'name' => 'Vitamin C 500mg Tablet',
        'base_uom_id' => $stripUomId,
        'track_expired' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('item_uom_conversions')->insert([
        'item_id' => $itemId,
        'from_uom_id' => $boxUomId,
        'to_uom_id' => $stripUomId,
        'factor' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_ledgers')->insert([
        [
            'trx_type' => 'USAGE_OUT',
            'trx_id' => 8,
            'trx_line_id' => 1,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'batch_id' => null,
            'qty_base' => -10,
            'uom_id' => $stripUomId,
            'qty_input' => 10,
            'unit_cost' => 200000,
            'trx_datetime' => now()->subSecond(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'trx_type' => 'USAGE_OUT',
            'trx_id' => 2,
            'trx_line_id' => 2,
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'batch_id' => null,
            'qty_base' => -10,
            'uom_id' => $boxUomId,
            'qty_input' => 2,
            'unit_cost' => 200000,
            'trx_datetime' => now(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    get(route('apps.reports.inventory.index', ['type' => 'item-usage']), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertJsonPath('props.reportData.rows.0.uom_name', 'BOX')
        ->assertJsonPath('props.reportData.rows.0.qty', 2.0)
        ->assertJsonPath('props.reportData.rows.0.unit_price', 1000000.0)
        ->assertJsonPath('props.reportData.rows.0.value', 2000000.0)
        ->assertJsonPath('props.reportData.rows.1.uom_name', 'STRIP')
        ->assertJsonPath('props.reportData.rows.1.unit_price', 200000.0)
        ->assertJsonPath('props.reportData.rows.1.value', 2000000.0);
});

it('shows batch number and expiry date on incoming and outgoing inventory reports', function () {
    $user = User::factory()->create();
    $permission = Permission::findOrCreate('inventory-reports-access', 'web');
    $user->givePermissionTo($permission);
    actingAs($user);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-BATCH',
        'name' => 'Warehouse Batch',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('items')->insertGetId([
        'sku' => 'BATCH-ITEM-01',
        'name' => 'Batch Report Item',
        'base_uom_id' => $uomId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $receivingEntryId = DB::table('receiving_entries')->insertGetId([
        'number' => 'RCV-BATCH-001',
        'warehouse_id' => $warehouseId,
        'transaction_date' => '2026-05-10',
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-BATCH-001',
        'vendor_name' => 'Vendor Batch',
        'total_value' => 50000,
        'status' => 'POSTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('receiving_entry_lines')->insert([
        'receiving_entry_id' => $receivingEntryId,
        'item_id' => $itemId,
        'uom_id' => $uomId,
        'qty' => 5,
        'price' => 10000,
        'value' => 50000,
        'batch_number' => 'IN-BATCH-001',
        'expired_date' => '2027-01-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $batchId = DB::table('item_batches')->insertGetId([
        'item_id' => $itemId,
        'batch_no' => 'OUT-BATCH-001',
        'expired_date' => '2027-02-28',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_ledgers')->insert([
        'trx_type' => 'USAGE_OUT',
        'trx_id' => 99,
        'trx_line_id' => 1,
        'warehouse_id' => $warehouseId,
        'item_id' => $itemId,
        'batch_id' => $batchId,
        'qty_base' => -2,
        'uom_id' => $uomId,
        'qty_input' => 2,
        'unit_cost' => 10000,
        'trx_datetime' => '2026-05-11 10:00:00',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    get(route('apps.reports.inventory.index', ['type' => 'incoming-items']), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertJsonPath('props.reportData.rows.0.batch_number', 'IN-BATCH-001')
        ->assertJsonPath('props.reportData.rows.0.expired_date', '2027-01-31');

    get(route('apps.reports.inventory.index', ['type' => 'item-usage']), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertJsonPath('props.reportData.rows.0.batch_number', 'OUT-BATCH-001')
        ->assertJsonPath('props.reportData.rows.0.expired_date', '2027-02-28');
});

it('shows batch and expiry date on inventory card incoming and outgoing tabs', function () {
    $user = User::factory()->create();
    actingAs($user);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-CARD',
        'name' => 'Warehouse Card',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $itemId = DB::table('items')->insertGetId([
        'sku' => 'CARD-BATCH-ITEM-01',
        'name' => 'Inventory Card Batch Report Item With A Longer Name For Wrapping',
        'base_uom_id' => $uomId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $receivingEntryId = DB::table('receiving_entries')->insertGetId([
        'number' => 'RCV-CARD-BATCH-001',
        'warehouse_id' => $warehouseId,
        'transaction_date' => '2026-05-12',
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-CARD-BATCH-001',
        'vendor_name' => 'Vendor Card Batch',
        'total_value' => 75000,
        'status' => 'POSTED',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('receiving_entry_lines')->insert([
        'receiving_entry_id' => $receivingEntryId,
        'item_id' => $itemId,
        'uom_id' => $uomId,
        'qty' => 3,
        'price' => 25000,
        'value' => 75000,
        'batch_number' => 'CARD-IN-BATCH-001',
        'expired_date' => '2027-03-31',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $batchId = DB::table('item_batches')->insertGetId([
        'item_id' => $itemId,
        'batch_no' => 'CARD-OUT-BATCH-001',
        'expired_date' => '2027-04-30',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_ledgers')->insert([
        'trx_type' => 'USAGE_OUT',
        'trx_id' => 199,
        'trx_line_id' => 1,
        'warehouse_id' => $warehouseId,
        'item_id' => $itemId,
        'batch_id' => $batchId,
        'qty_base' => -1,
        'uom_id' => $uomId,
        'qty_input' => 1,
        'unit_cost' => 25000,
        'trx_datetime' => '2026-05-13 10:00:00',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    get(route('apps.inventory.items.card', [$itemId, 'tab' => 'barang-masuk']), [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertJsonPath('props.incomingReportData.rows.0.batch_number', 'CARD-IN-BATCH-001')
        ->assertJsonPath('props.incomingReportData.rows.0.expired_date', '2027-03-31')
        ->assertJsonPath('props.outgoingReportData.rows.0.batch_number', 'CARD-OUT-BATCH-001')
        ->assertJsonPath('props.outgoingReportData.rows.0.expired_date', '2027-04-30');
});
