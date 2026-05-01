<?php

use App\Models\Inventory\Item;
use App\Models\Regulatory\RegulatoryProduct;
use App\Models\Regulatory\RegulatorySource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('can create regulatory product', function () {
    $user = User::factory()->create();
    $source = RegulatorySource::firstOrCreate(['source_name' => 'BPOM']);
    $this->actingAs($user)->post('/apps/master-data/regulatory-products', [
        'source_id' => $source->id,
        'nie' => 'NIE-001',
        'product_name_source' => 'Paracetamol',
    ])->assertRedirect();

    $this->assertDatabaseHas('regulatory_products', ['nie' => 'NIE-001']);
});

it('can map and set primary regulatory product', function () {
    $user = User::factory()->create();
    $uomId = DB::table('uoms')->insertGetId(['code'=>'PCS-T','name'=>'PCS T','created_at'=>now(),'updated_at'=>now()]);
    $item = Item::create(['sku'=>'SKU-T1','name'=>'Item T1','base_uom_id'=>$uomId,'is_active'=>1]);
    $source = RegulatorySource::firstOrCreate(['source_name' => 'BPOM']);
    $rp = RegulatoryProduct::create(['source_id'=>$source->id,'nie'=>'NIE-XYZ','product_name_source'=>'X']);

    $this->actingAs($user)->post('/apps/master-data/regulatory-products/mapping/attach', [
        'item_id'=>$item->id,'regulatory_product_id'=>$rp->id,
    ])->assertRedirect();

    $this->actingAs($user)->post('/apps/master-data/regulatory-products/mapping/set-primary', [
        'item_id'=>$item->id,'regulatory_product_id'=>$rp->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('item_regulatory_products',['item_id'=>$item->id,'regulatory_product_id'=>$rp->id,'is_primary'=>1]);
});

it('can search regulatory products by source name including custom sources', function () {
    $user = User::factory()->create();
    $source = RegulatorySource::firstOrCreate(['source_name' => 'BOSKA']);
    RegulatoryProduct::create([
        'source_id' => $source->id,
        'nie' => 'NIE-BOSKA-001',
        'product_name_source' => 'Produk Uji BOSKA',
    ]);

    $this->actingAs($user)
        ->getJson('/apps/master-data/regulatory-products/search?q=BOSKA')
        ->assertOk()
        ->assertJsonPath('data.0.source_name', 'BOSKA');
});
