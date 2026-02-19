<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

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

it('stores receiving entry with multi line items and auto value', function () {
    post(route('apps.inbound.receiving.store'), [
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
        ->and($entry->transaction_code)->toBe('PEMBELIAN')
        ->and((float) $entry->total_value)->toBe(35000.0)
        ->and($entry->vendor_name)->toBe('Vendor Bebas');

    $lines = DB::table('receiving_entry_lines')->orderBy('id')->get();

    expect($lines)->toHaveCount(2)
        ->and((float) $lines[0]->value)->toBe(20000.0)
        ->and((float) $lines[1]->value)->toBe(15000.0);
});
