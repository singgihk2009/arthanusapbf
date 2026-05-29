<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
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



it('generates next receiving number from max sequence even when there are gaps', function () {
    $today = now()->format('Ymd');

    DB::table('receiving_entries')->insert([
        [
            'number' => "RCV-PBL-$today-0001",
            'warehouse_id' => $this->warehouseId,
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_code' => 'PEMBELIAN',
            'total_value' => 0,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'number' => "RCV-PBL-$today-0002",
            'warehouse_id' => $this->warehouseId,
            'transaction_date' => now()->format('Y-m-d'),
            'transaction_code' => 'PEMBELIAN',
            'total_value' => 0,
            'status' => 'DRAFT',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('receiving_entries')->where('number', "RCV-PBL-$today-0001")->delete();

    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-GAP-001',
        'vendor_name' => 'Vendor Gap',
        'lines' => [
            [
                'item_id' => $this->itemId,
                'qty' => 1,
                'uom_id' => $this->uomId,
                'price' => 1000,
            ],
        ],
    ])->assertRedirect();

    $latestNumber = DB::table('receiving_entries')->orderByDesc('id')->value('number');

    expect($latestNumber)->toBe("RCV-PBL-$today-0003");
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

it('removes stock ledger history after receiving is unposted then deleted', function () {
    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-DEL-01',
        'vendor_name' => 'Vendor Delete',
        'lines' => [[
            'item_id' => $this->itemId,
            'qty' => 4,
            'uom_id' => $this->uomId,
            'price' => 5000,
        ]],
    ])->assertRedirect();

    $entryId = DB::table('receiving_entries')->value('id');

    postJson(route('apps.inventory.posting.receiving', $entryId))->assertOk();
    postJson(route('apps.inventory.unposting.receiving', $entryId))->assertOk();

    expect(DB::table('stock_ledgers')->where('trx_type', 'RCV_IN')->where('trx_id', $entryId)->count())
        ->toBeGreaterThan(0);

    delete(route('apps.inbound.receiving.destroy', $entryId))
        ->assertRedirect();

    expect(DB::table('stock_ledgers')->where('trx_type', 'RCV_IN')->where('trx_id', $entryId)->count())
        ->toBe(0)
        ->and(DB::table('receiving_entries')->where('id', $entryId)->exists())
        ->toBeFalse();
});

it('normalizes receiving unit cost to base uom when posting with converted uom', function () {
    $boxUomId = DB::table('uoms')->insertGetId([
        'code' => 'BOX',
        'name' => 'Box',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('item_uom_conversions')->insert([
        'item_id' => $this->itemId,
        'uom_id' => $boxUomId,
        'factor' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => now()->format('Y-m-d'),
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'PO-UOM-001',
        'vendor_name' => 'Vendor UOM',
        'lines' => [
            [
                'item_id' => $this->itemId,
                'qty' => 5,
                'uom_id' => $boxUomId,
                'price' => 100000,
            ],
        ],
    ])->assertRedirect();

    $entryId = DB::table('receiving_entries')->value('id');

    postJson(route('apps.inventory.posting.receiving', $entryId))
        ->assertOk();

    $ledger = DB::table('stock_ledgers')
        ->where('trx_type', 'RCV_IN')
        ->where('trx_id', $entryId)
        ->first();

    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->qty_base)->toBe(500.0)
        ->and((float) $ledger->qty_input)->toBe(5.0)
        ->and((float) $ledger->unit_cost)->toBe(1000.0);
});

it('sends posted receiving entry payload to finance hub and records integration history', function () {
    config()->set('services.finance_hub.events_url', 'https://finance-hub.test/api/integrations/inventory/events');
    config()->set('services.finance_hub.client_key', 'INVENTORY-WSPRYNF677FY');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    \Illuminate\Support\Facades\Http::fake([
        'finance-hub.test/*' => \Illuminate\Support\Facades\Http::response(['message' => 'ok'], 200),
    ]);

    post(route('apps.inbound.receiving.store'), [
        'warehouse_id' => $this->warehouseId,
        'transaction_date' => '2026-03-28',
        'transaction_code' => 'PEMBELIAN',
        'reference' => 'GRN-TEST-0011',
        'vendor_name' => 'Vendor Finance Hub',
        'notes' => 'Test Inventory Receipt',
        'lines' => [[
            'item_id' => $this->itemId,
            'qty' => 20,
            'uom_id' => $this->uomId,
            'price' => 25000,
        ]],
    ])->assertRedirect();

    $entryId = DB::table('receiving_entries')->value('id');

    postJson(route('apps.inventory.posting.receiving', $entryId))
        ->assertOk()
        ->assertJsonPath('message', 'Receiving entry posted');

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://finance-hub.test/api/integrations/inventory/events'
            && $payload['client_key'] === 'INVENTORY-WSPRYNF677FY'
            && $payload['client_secret'] === 'secret-test'
            && $payload['event_name'] === 'inventory.receipt.posted'
            && $payload['source_document_type'] === 'goods_receipt'
            && $payload['payload']['transaction_type'] === 'inventory.receipt.posted'
            && $payload['payload']['posting_date'] === '2026-03-28'
            && $payload['payload']['currency_code'] === 'IDR'
            && $payload['payload']['warehouse_code'] === 'WH-RCV'
            && $payload['payload']['total_amount'] === 500000.0
            && $payload['payload']['lines'][0]['item_code'] === 'ITEM-RCV-01'
            && $payload['payload']['lines'][0]['qty'] === 20.0
            && $payload['payload']['lines'][0]['unit_cost'] === 25000.0;
    });

    $transaction = DB::table('inv_transactions')
        ->where('source_table', 'receiving_entries')
        ->where('source_id', $entryId)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->trx_type)->toBe('RECEIPT')
        ->and($transaction->gl_status)->toBe('sent');

    $outbox = DB::table('integration_outbox')->where('aggregate_id', $transaction->id)->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->event_type)->toBe('inventory.receipt.posted')
        ->and($outbox->status)->toBe('sent')
        ->and((int) $outbox->attempts)->toBe(1);
});
