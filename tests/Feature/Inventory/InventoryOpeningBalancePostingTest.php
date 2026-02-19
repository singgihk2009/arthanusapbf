<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $permission = Permission::findOrCreate('inventory-posting-opening-balance', 'web');
    $this->user->givePermissionTo($permission);

    actingAs($this->user);

    $this->warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WH-OB',
        'name' => 'Warehouse Opening Balance',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->baseUomId = DB::table('uoms')->insertGetId([
        'code' => 'PCS',
        'name' => 'Pieces',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->boxUomId = DB::table('uoms')->insertGetId([
        'code' => 'BOX',
        'name' => 'Box',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->itemId = DB::table('items')->insertGetId([
        'sku' => 'ITEM-OB-01',
        'name' => 'Opening Balance Item',
        'base_uom_id' => $this->baseUomId,
        'track_expired' => false,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('item_uom_conversions')->insert([
        'item_id' => $this->itemId,
        'from_uom_id' => $this->boxUomId,
        'to_uom_id' => $this->baseUomId,
        'factor' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('renders opening balance page', function () {
    get(route('apps.inventory.opening-balance.index'))
        ->assertOk();
});

it('posts opening balance with qty, uom, and unit cost', function () {
    postJson(route('apps.inventory.posting.opening-balance'), [
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'qty' => 2,
        'uom_id' => $this->boxUomId,
        'unit_cost' => 15000,
    ])->assertOk()->assertJsonPath('message', 'Opening balance posted');

    $ledger = DB::table('stock_ledgers')->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->trx_type)->toBe('OPENING_BALANCE')
        ->and((float) $ledger->qty_input)->toBe(2.0)
        ->and((float) $ledger->qty_base)->toBe(20.0)
        ->and((float) $ledger->unit_cost)->toBe(15000.0)
        ->and((int) $ledger->uom_id)->toBe($this->boxUomId);

    $balance = DB::table('stock_balances')->first();

    expect($balance)->not->toBeNull()
        ->and((float) $balance->on_hand_base)->toBe(20.0);
});

it('imports opening balance from csv template', function () {
    $csv = "warehouse_code,item_sku,qty,uom_code,unit_cost,batch_no,trx_datetime\n".
        "WH-OB,ITEM-OB-01,3,BOX,20000,B-1,".now()->format('Y-m-d H:i:s')."\n";

    $file = UploadedFile::fake()->createWithContent('opening.csv', $csv);

    postJson(route('apps.inventory.opening-balance.import'), [
        'file' => $file,
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('errors', []);

    $ledger = DB::table('stock_ledgers')->first();

    expect($ledger)->not->toBeNull()
        ->and($ledger->trx_type)->toBe('OPENING_BALANCE')
        ->and((float) $ledger->qty_base)->toBe(30.0)
        ->and((float) $ledger->unit_cost)->toBe(20000.0);
});

it('downloads csv and excel templates', function () {
    get(route('apps.inventory.opening-balance.template.csv'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    get(route('apps.inventory.opening-balance.template.excel'))
        ->assertOk();
});

it('validates required fields for opening balance input', function () {
    postJson(route('apps.inventory.posting.opening-balance'), [
        'warehouse_id' => $this->warehouseId,
        'item_id' => $this->itemId,
        'qty' => 0,
        'uom_id' => $this->boxUomId,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['qty', 'unit_cost']);
});
