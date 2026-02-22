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
    $permission = Permission::findOrCreate('inventory-posting-usage', 'web');
    $this->user->givePermissionTo($permission);
    actingAs($this->user);

    $this->warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-USG',
        'name' => 'Warehouse Usage',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->uomPcsId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
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
        'sku' => 'ITEM-USG-01',
        'name' => 'Usage Item',
        'base_uom_id' => $this->uomPcsId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('item_uom_conversions')->insert([
        'item_id' => $this->itemId,
        'from_uom_id' => $this->uomBoxId,
        'to_uom_id' => $this->uomPcsId,
        'factor' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('posts usage with batch valuation when batch number is provided', function () {
    $batchId = DB::table('item_batches')->insertGetId([
        'item_id' => $this->itemId,
        'batch_no' => 'BAT-001',
        'expired_date' => now()->addMonth()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('inv_batches')->insert([
        'company_id' => 1,
        'warehouse_id' => $this->warehouseId,
        'product_id' => $this->itemId,
        'batch_no' => 'BAT-001',
        'expired_date' => now()->addMonth()->toDateString(),
        'unit_cost' => 25000,
        'qty_on_hand' => 100,
        'stock_value' => 2500000,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.outbound.internal-usage.store'), [
        'warehouse_id' => $this->warehouseId,
        'document_date' => now()->toDateString(),
        'lines' => [[
            'item_id' => $this->itemId,
            'batch_id' => $batchId,
            'qty_used' => 2,
            'uom_id' => $this->uomBoxId,
        ]],
    ])->assertRedirect();

    $usageId = DB::table('internal_usages')->value('id');

    postJson(route('apps.inventory.posting.usage', $usageId))
        ->assertOk();

    $ledger = DB::table('stock_ledgers')
        ->where('trx_type', 'USAGE_OUT')
        ->where('trx_id', $usageId)
        ->first();

    expect((int) $ledger->batch_id)->toBe($batchId)
        ->and((float) $ledger->qty_base)->toBe(-20.0)
        ->and((float) $ledger->unit_cost)->toBe(25000.0);
});

it('posts usage with average valuation when batch number is empty', function () {
    DB::table('inv_balances')->insert([
        'company_id' => 1,
        'warehouse_id' => $this->warehouseId,
        'product_id' => $this->itemId,
        'avg_cost' => 12000,
        'on_hand_qty' => 200,
        'stock_value' => 2400000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.outbound.internal-usage.store'), [
        'warehouse_id' => $this->warehouseId,
        'document_date' => now()->toDateString(),
        'lines' => [[
            'item_id' => $this->itemId,
            'qty_used' => 3,
            'uom_id' => $this->uomBoxId,
        ]],
    ])->assertRedirect();

    $usageId = DB::table('internal_usages')->value('id');

    postJson(route('apps.inventory.posting.usage', $usageId))
        ->assertOk();

    $line = DB::table('internal_usage_lines')->where('internal_usage_id', $usageId)->first();
    $ledger = DB::table('stock_ledgers')
        ->where('trx_type', 'USAGE_OUT')
        ->where('trx_id', $usageId)
        ->first();

    expect((float) $line->qty_base)->toBe(30.0)
        ->and($ledger->batch_id)->toBeNull()
        ->and((float) $ledger->qty_base)->toBe(-30.0)
        ->and((float) $ledger->unit_cost)->toBe(12000.0);
});
