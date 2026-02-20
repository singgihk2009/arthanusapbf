<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-ADJ',
        'name' => 'Warehouse Adjustment',
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
        'sku' => 'ITEM-ADJ-01',
        'name' => 'Adjustment Item',
        'base_uom_id' => $this->uomId,
        'track_expired' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('posts stock adjustment and updates stock balance', function () {
    post(route('apps.outbound.stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseId,
        'document_date' => now()->format('Y-m-d'),
        'reason_code' => 'CORRECTION',
        'notes' => 'Tambah stok',
        'lines' => [[
            'item_id' => $this->itemId,
            'qty_adjusted' => 10,
            'uom_id' => $this->uomId,
        ]],
    ])->assertRedirect();

    $adjustmentId = DB::table('stock_adjustments')->value('id');

    postJson(route('apps.inventory.posting.adjustment', $adjustmentId))
        ->assertOk();

    $stock = DB::table('stock_balances')
        ->where('warehouse_id', $this->warehouseId)
        ->where('item_id', $this->itemId)
        ->value('on_hand_base');

    expect((float) $stock)->toBe(10.0);
});

it('posts stock opname and auto generates posted adjustment', function () {
    DB::table('stock_balances')->insert([
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'batch_id' => null,
        'on_hand_base' => 7,
        'reserved_base' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.outbound.stock-opname.store'), [
        'warehouse_id' => $this->warehouseId,
        'document_date' => now()->format('Y-m-d'),
        'type' => 'CYCLE',
        'notes' => 'Hitung ulang',
        'lines' => [[
            'item_id' => $this->itemId,
            'counted_qty_base' => 5,
        ]],
    ])->assertRedirect();

    $opnameId = DB::table('stock_opnames')->value('id');

    postJson(route('apps.inventory.posting.opname', $opnameId))
        ->assertOk();

    $opname = DB::table('stock_opnames')->where('id', $opnameId)->first();
    $adjustment = DB::table('stock_adjustments')->where('id', $opname->adjustment_id)->first();

    expect($opname->status)->toBe('POSTED')
        ->and($adjustment)->not->toBeNull()
        ->and($adjustment->reason_code)->toBe('OPNAME')
        ->and($adjustment->status)->toBe('POSTED');
});
