<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $permission = Permission::findOrCreate('inventory-posting-transfer', 'web');
    $this->user->givePermissionTo($permission);
    actingAs($this->user);

    $this->fromWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-SRC',
        'name' => 'Gudang Asal',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->toWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-DST',
        'name' => 'Gudang Tujuan',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->uomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->itemId = DB::table('items')->insertGetId([
        'sku' => 'ITEM-TRF-01',
        'name' => 'Transfer Item',
        'base_uom_id' => $this->uomId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('posts transfer using avg valuation when batch is empty', function () {
    DB::table('inv_balances')->insert([
        'company_id' => 1,
        'warehouse_id' => $this->fromWarehouseId,
        'product_id' => $this->itemId,
        'on_hand_qty' => 20,
        'avg_cost' => 150000,
        'stock_value' => 3000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.transfer.warehouse.store'), [
        'from_warehouse_id' => $this->fromWarehouseId,
        'to_warehouse_id' => $this->toWarehouseId,
        'document_date' => now()->toDateString(),
        'lines' => [[
            'item_id' => $this->itemId,
            'qty_requested' => 5,
            'uom_id' => $this->uomId,
        ]],
    ])->assertRedirect();

    $transferId = DB::table('warehouse_transfers')->value('id');

    postJson(route('apps.inventory.posting.transfer', $transferId))
        ->assertOk();

    $outLedger = DB::table('stock_ledgers')
        ->where('trx_type', 'TRANSFER_OUT')
        ->where('trx_id', $transferId)
        ->first();

    $inLedger = DB::table('stock_ledgers')
        ->where('trx_type', 'TRANSFER_IN')
        ->where('trx_id', $transferId)
        ->first();

    expect((float) $outLedger->unit_cost)->toBe(150000.0)
        ->and((float) $inLedger->unit_cost)->toBe(150000.0)
        ->and($outLedger->batch_id)->toBeNull()
        ->and($inLedger->batch_id)->toBeNull();
});

it('posts transfer using batch valuation when batch is selected', function () {
    $batchId = DB::table('item_batches')->insertGetId([
        'item_id' => $this->itemId,
        'batch_no' => 'BAT-TRF-01',
        'expired_date' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('inv_batches')->insert([
        'company_id' => 1,
        'warehouse_id' => $this->fromWarehouseId,
        'product_id' => $this->itemId,
        'batch_no' => 'BAT-TRF-01',
        'expired_date' => now()->addMonth()->toDateString(),
        'qty_on_hand' => 10,
        'unit_cost' => 200000,
        'stock_value' => 2000000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.transfer.warehouse.store'), [
        'from_warehouse_id' => $this->fromWarehouseId,
        'to_warehouse_id' => $this->toWarehouseId,
        'document_date' => now()->toDateString(),
        'lines' => [[
            'item_id' => $this->itemId,
            'batch_id' => $batchId,
            'qty_requested' => 4,
            'uom_id' => $this->uomId,
        ]],
    ])->assertRedirect();

    $transferId = DB::table('warehouse_transfers')->value('id');

    postJson(route('apps.inventory.posting.transfer', $transferId))
        ->assertOk();

    $outLedger = DB::table('stock_ledgers')
        ->where('trx_type', 'TRANSFER_OUT')
        ->where('trx_id', $transferId)
        ->first();

    $inLedger = DB::table('stock_ledgers')
        ->where('trx_type', 'TRANSFER_IN')
        ->where('trx_id', $transferId)
        ->first();

    expect((int) $outLedger->batch_id)->toBe($batchId)
        ->and((int) $inLedger->batch_id)->toBe($batchId)
        ->and((float) $outLedger->unit_cost)->toBe(200000.0)
        ->and((float) $inLedger->unit_cost)->toBe(200000.0);
});
