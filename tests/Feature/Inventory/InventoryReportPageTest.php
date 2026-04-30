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
