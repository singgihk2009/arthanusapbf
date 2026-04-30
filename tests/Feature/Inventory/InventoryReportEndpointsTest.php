<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    foreach ([
        'report-stock-balance',
        'report-stock-card',
        'report-expired-soon',
        'report-minimum-stock-alerts',
    ] as $permissionName) {
        $permission = Permission::findOrCreate($permissionName, 'web');
        $this->user->givePermissionTo($permission);
    }

    actingAs($this->user);

    // minimal master setup
    $this->warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-01',
        'name' => 'Warehouse 01',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $uomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->itemId = DB::table('items')->insertGetId([
        'sku' => 'SKU-001',
        'name' => 'Sample Item',
        'base_uom_id' => $uomId,
        'track_expired' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('warehouse_item_settings')->insert([
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'min_stock_base' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_balances')->insert([
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => null,
        'on_hand_base' => 3,
        'reserved_base' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_ledgers')->insert([
        'trx_type' => 'ADJ',
        'trx_id' => 1,
        'trx_line_id' => 1,
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => null,
        'qty_base' => 10,
        'uom_id' => $uomId,
        'qty_input' => 10,
        'unit_cost' => null,
        'trx_datetime' => now()->subDay(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_ledgers')->insert([
        'trx_type' => 'SALE_OUT',
        'trx_id' => 2,
        'trx_line_id' => 1,
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => null,
        'qty_base' => -7,
        'uom_id' => $uomId,
        'qty_input' => 7,
        'unit_cost' => null,
        'trx_datetime' => now(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('returns stock balance report', function () {
    getJson(route('apps.reports.inventory.stock-balance'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('returns stock card report with running balance', function () {
    getJson(route('apps.reports.inventory.stock-card', [
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->toDateString(),
    ]))
        ->assertOk()
        ->assertJsonPath('closing_balance', 3.0)
        ->assertJsonStructure(['opening_balance', 'rows', 'closing_balance']);
});

it('returns minimum stock alerts', function () {
    getJson(route('apps.reports.inventory.minimum-stock-alerts'))
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('returns expired tracking alerts with severity status', function () {
    DB::table('items')->where('id', $this->itemId)->update([
        'track_expired' => true,
        'updated_at' => now(),
    ]);

    $batchId = DB::table('item_batches')->insertGetId([
        'item_id' => $this->itemId,
        'batch_no' => 'BATCH-EXP-01',
        'expired_date' => now()->subDay()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('stock_balances')->where([
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => null,
    ])->delete();

    DB::table('stock_balances')->insert([
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => $batchId,
        'on_hand_base' => 6,
        'reserved_base' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    getJson(route('apps.reports.inventory.expired-soon', ['days' => 30]))
        ->assertOk()
        ->assertJsonPath('data.0.batch_no', 'BATCH-EXP-01')
        ->assertJsonPath('data.0.status', 'EXPIRED');
});
