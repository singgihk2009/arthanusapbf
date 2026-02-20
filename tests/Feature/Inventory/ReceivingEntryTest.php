<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Permission::findOrCreate('inventory-posting-grn', 'web');
    Permission::findOrCreate('report-stock-balance', 'web');
    $this->user->givePermissionTo(['inventory-posting-grn', 'report-stock-balance']);

    actingAs($this->user);

    $this->warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-RCV',
        'name' => 'Warehouse Receiving',
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
        'sku' => 'ITEM-RCV-01',
        'name' => 'Receiving Item',
        'base_uom_id' => $this->uomId,
        'track_expired' => true,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('renders receiving entry page', function () {
    get(route('apps.inbound.receiving.index'))
        ->assertOk();
});

it('stores receiving entry with warehouse and multi line items and auto value', function () {
    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-12345',
        'vendor_name' => 'Vendor Bebas',
        'notes' => 'Dokumen barang masuk',
        'lines' => [
            [
                'item_id' => $this->itemId,
                'qty' => 2,
                'uom_id' => $this->uomId,
                'price' => 10000,
                'batch_number' => 'B001',
                'expired_date' => now()->addMonth()->format('Y-m-d'),
                'notes' => 'Line 1',
            ],
            [
                'item_id' => $this->itemId,
                'qty' => 3,
                'uom_id' => $this->uomId,
                'price' => 5000,
                'batch_number' => 'B002',
                'expired_date' => now()->addMonths(2)->format('Y-m-d'),
                'notes' => 'Line 2',
            ],
        ],
    ])->assertRedirect();

    $entry = DB::table('receiving_entries')->first();

    expect($entry)->not->toBeNull()
        ->and((int) $entry->warehouse_id)->toBe($this->warehouseId)
        ->and($entry->transaction_code)->toBe('PEMBELIAN')
        ->and((float) $entry->total_value)->toBe(35000.0)
        ->and($entry->vendor_name)->toBe('Vendor Bebas')
        ->and($entry->status)->toBe('DRAFT');

    $lines = DB::table('receiving_entry_lines')->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and((float) $lines[0]->value)->toBe(20000.0)
        ->and((float) $lines[1]->value)->toBe(15000.0);
});


it('posts receiving entry and increases stock balance', function () {
    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-POST-01',
        'vendor_name' => 'Vendor Posting',
        'lines' => [
            [
                'item_id' => $this->itemId,
                'qty' => 5,
                'uom_id' => $this->uomId,
                'price' => 2000,
                'batch_number' => 'POST-BATCH-1',
                'expired_date' => now()->addMonths(3)->format('Y-m-d'),
            ],
        ],
    ])->assertRedirect();

    $entryId = DB::table('receiving_entries')->value('id');

    postJson(route('apps.inventory.posting.receiving', $entryId))
        ->assertOk()
        ->assertJsonPath('message', 'Receiving entry posted');

    $entry = DB::table('receiving_entries')->where('id', $entryId)->first();

    expect($entry->status)->toBe('POSTED')
        ->and($entry->posted_by)->toBe($this->user->id)
        ->and($entry->posted_at)->not->toBeNull();

    $stockBalance = DB::table('stock_balances')
        ->where('warehouse_id', $this->warehouseId)
        ->where('item_id', $this->itemId)
        ->sum('on_hand_base');

    expect((float) $stockBalance)->toBe(5.0);

    $report = get(route('apps.reports.inventory.stock-balance'));
    $report->assertOk();

    $rows = data_get($report->json(), 'data.data', []);

    expect(collect($rows)->contains(fn (array $row) => (int) $row['warehouse_id'] === $this->warehouseId
        && (int) $row['item_id'] === $this->itemId
        && (float) $row['on_hand_base'] === 5.0))->toBeTrue();
});
