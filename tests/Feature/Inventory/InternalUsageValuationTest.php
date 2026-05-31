<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
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

it('removes stock ledger history after internal usage is unposted then deleted', function () {
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
            'qty_used' => 2,
            'uom_id' => $this->uomBoxId,
        ]],
    ])->assertRedirect();

    $usageId = DB::table('internal_usages')->value('id');

    postJson(route('apps.inventory.posting.usage', $usageId))->assertOk();
    postJson(route('apps.inventory.unposting.usage', $usageId))->assertOk();

    expect(DB::table('stock_ledgers')->where('trx_type', 'USAGE_OUT')->where('trx_id', $usageId)->count())
        ->toBeGreaterThan(0);

    delete(route('apps.outbound.internal-usage.destroy', $usageId))
        ->assertRedirect();

    expect(DB::table('stock_ledgers')->where('trx_type', 'USAGE_OUT')->where('trx_id', $usageId)->count())
        ->toBe(0)
        ->and(DB::table('internal_usages')->where('id', $usageId)->exists())
        ->toBeFalse();
});

it('sends posted dispatch issue payload to finance hub and records outbox for each supported transaction code', function (string $transactionCode, string $sourceDocumentType, string $payloadTransactionType, string $extraKey, string $extraValue) {
    config()->set('services.finance_hub.events_url', 'https://finance-hub.test/api/integrations/inventory/events');
    config()->set('services.finance_hub.client_key', 'INVENTORY-WSPRYNF677FY');
    config()->set('services.finance_hub.client_secret', 'secret-test');

    \Illuminate\Support\Facades\Http::fake([
        'finance-hub.test/*' => \Illuminate\Support\Facades\Http::response(['message' => 'ok'], 200),
    ]);

    DB::table('inv_balances')->insert([
        'company_id' => 1,
        'warehouse_id' => $this->warehouseId,
        'product_id' => $this->itemId,
        'avg_cost' => 45000,
        'on_hand_qty' => 200,
        'stock_value' => 9000000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customerId = null;
    if ($transactionCode === 'PENJUALAN') {
        $customerId = DB::table('customers')->insertGetId([
            'customer_code' => $extraValue,
            'customer_name' => 'Customer Dispatch',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $usageId = DB::table('internal_usages')->insertGetId([
        'number' => 'IUS-TEST-'.$transactionCode,
        'warehouse_id' => $this->warehouseId,
        'transaction_code' => $transactionCode,
        'outbound_number' => 'OUT-'.$transactionCode,
        'sender_receiver_name' => $transactionCode === 'SAMPLE' ? $extraValue : 'Recipient Dispatch',
        'department' => $transactionCode === 'INTERNAL_USE' ? $extraValue : 'Ops',
        'cost_center' => null,
        'document_date' => '2026-03-28',
        'status' => 'DRAFT',
        'notes' => $transactionCode === 'DAMAGED' ? $extraValue : 'Dispatch issue test',
        'customer_id' => $customerId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('internal_usage_lines')->insert([
        'internal_usage_id' => $usageId,
        'item_id' => $this->itemId,
        'qty_used' => 2,
        'uom_id' => $this->uomPcsId,
        'qty_base' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    postJson(route('apps.inventory.posting.usage', $usageId))
        ->assertOk()
        ->assertJsonPath('message', 'Internal usage posted');

    \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($sourceDocumentType, $payloadTransactionType, $extraKey, $extraValue, $transactionCode): bool {
        $payload = $request->data();

        return $request->url() === 'https://finance-hub.test/api/integrations/inventory/events'
            && $payload['client_key'] === 'INVENTORY-WSPRYNF677FY'
            && $payload['client_secret'] === 'secret-test'
            && $payload['event_name'] === 'inventory.issue.posted'
            && str_starts_with($payload['idempotency_key'], 'INV-ISSUE-')
            && $payload['source_document_type'] === $sourceDocumentType
            && $payload['source_document_no'] === 'OUT-'.$transactionCode
            && $payload['payload']['transaction_type'] === $payloadTransactionType
            && $payload['payload']['posting_date'] === '2026-03-28'
            && $payload['payload']['entry_date'] === '2026-03-28'
            && $payload['payload']['currency_code'] === 'IDR'
            && $payload['payload']['exchange_rate'] === 1
            && $payload['payload']['reference_no'] === 'OUT-'.$transactionCode
            && $payload['payload']['total_amount'] === 90000.0
            && $payload['payload']['branch_code'] === 'MAIN'
            && $payload['payload']['warehouse_code'] === 'WH-USG'
            && $payload['payload'][$extraKey] === $extraValue
            && $payload['payload']['lines'][0]['item_code'] === 'ITEM-USG-01'
            && $payload['payload']['lines'][0]['item_name'] === 'Usage Item'
            && $payload['payload']['lines'][0]['qty'] === 2.0
            && $payload['payload']['lines'][0]['unit_cost'] === 45000.0
            && $payload['payload']['lines'][0]['uom'] === 'PCS';
    });

    $transaction = DB::table('inv_transactions')
        ->where('source_table', 'internal_usages')
        ->where('source_id', $usageId)
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->trx_type)->toBe('USAGE')
        ->and($transaction->gl_status)->toBe('sent');

    $outbox = DB::table('integration_outbox')->where('aggregate_id', $transaction->id)->first();

    expect($outbox)->not->toBeNull()
        ->and($outbox->event_type)->toBe('inventory.issue.posted')
        ->and($outbox->status)->toBe('sent')
        ->and((int) $outbox->attempts)->toBe(1);
})->with([
    ['PENJUALAN', 'sales_order_issue', 'inventory.issue.sales', 'customer_code', 'CUST-001'],
    ['DAMAGED', 'inventory_write_off', 'inventory.issue.damaged', 'damage_reason', 'broken_in_warehouse'],
    ['SAMPLE', 'sample_issue', 'inventory.issue.sample', 'recipient_name', 'PT Prospect Baru'],
    ['INTERNAL_USE', 'internal_use_issue', 'inventory.issue.internal_use', 'department_code', 'OPS'],
]);
