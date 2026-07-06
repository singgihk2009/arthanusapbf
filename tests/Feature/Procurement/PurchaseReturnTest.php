<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['company_id' => 1]);
    actingAs($this->user);

    $this->warehouseId = DB::table('warehouses')->insertGetId(['code' => 'WH-PRR', 'name' => 'Warehouse PRR', 'created_at' => now(), 'updated_at' => now()]);
    $this->uomId = DB::table('uoms')->insertGetId(['code' => 'PCS', 'name' => 'Pieces', 'created_at' => now(), 'updated_at' => now()]);
    $this->itemId = DB::table('items')->insertGetId(['sku' => 'ITEM-PRR', 'name' => 'Return Item', 'base_uom_id' => $this->uomId, 'track_expired' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $this->vendorId = DB::table('vendors')->insertGetId(['company_id' => 1, 'vendor_code' => 'V-PRR', 'vendor_name' => 'Vendor Return', 'name' => 'Vendor Return', 'currency_code' => 'IDR', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
    $this->supplierId = DB::table('suppliers')->insertGetId(['code' => 'S-PRR', 'name' => 'Supplier Return', 'created_at' => now(), 'updated_at' => now()]);
    $this->poId = DB::table('purchase_orders')->insertGetId(['number' => 'PO-PRR-001', 'po_number' => 'PO-PRR-001', 'warehouse_id' => $this->warehouseId, 'supplier_id' => $this->supplierId, 'vendor_id' => $this->vendorId, 'document_date' => '2026-07-01', 'order_date' => '2026-07-01', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
    $this->receivingEntryId = DB::table('receiving_entries')->insertGetId(['number' => 'RCV-PBL-20260704-0001', 'warehouse_id' => $this->warehouseId, 'transaction_date' => '2026-07-02', 'transaction_code' => 'PEMBELIAN', 'source_type' => 'purchase_order', 'source_id' => $this->poId, 'reference' => 'PO-PRR-001', 'vendor_name' => 'Vendor Return', 'vendor_id' => $this->vendorId, 'total_value' => 250000, 'status' => 'posted', 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $this->receivingEntryLineId = DB::table('receiving_entry_lines')->insertGetId(['receiving_entry_id' => $this->receivingEntryId, 'source_item_id' => 1, 'item_id' => $this->itemId, 'qty' => 10, 'uom_id' => $this->uomId, 'price' => 25000, 'value' => 250000, 'batch_number' => 'B-EXP', 'expired_date' => '2026-07-31', 'inventory_unit_cost' => 25000, 'inventory_total_cost' => 250000, 'created_at' => now(), 'updated_at' => now()]);
});

it('lists posted receiving entries on purchase return create page', function () {
    get(route('apps.procurement.purchase-returns.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Apps/Procurement/PurchaseReturns/Form')
            ->where('receivingEntries.0.number', 'RCV-PBL-20260704-0001')
        );
});

it('posts purchase return, records stock out, and creates invoice deduction', function () {
    $invoiceId = DB::table('vendor_invoices')->insertGetId(['company_id' => 1, 'vendor_id' => $this->vendorId, 'invoice_no_internal' => 'VI-PRR-001', 'vendor_invoice_no' => 'SUP-PRR-001', 'invoice_date' => '2026-07-03', 'due_date' => '2026-08-03', 'currency_code' => 'IDR', 'exchange_rate' => 1, 'subtotal' => 250000, 'tax_amount' => 0, 'discount_amount' => 0, 'freight_amount' => 0, 'grand_total' => 250000, 'net_payable_amount' => 250000, 'paid_amount' => 0, 'outstanding_amount' => 250000, 'status' => 'POSTED', 'payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);

    post(route('apps.procurement.purchase-returns.store'), ['receiving_entry_id' => $this->receivingEntryId, 'return_date' => '2026-07-04', 'reason_category' => 'EXPIRED', 'lines' => [['receiving_entry_line_id' => $this->receivingEntryLineId, 'qty_returned' => 2, 'reason' => 'EXPIRED', 'condition_notes' => 'Expired saat inspeksi']]])->assertRedirect();

    $returnId = DB::table('purchase_returns')->value('id');
    post(route('apps.procurement.purchase-returns.approve', $returnId))->assertRedirect();
    post(route('apps.procurement.purchase-returns.post', $returnId))->assertRedirect();

    expect(DB::table('purchase_returns')->where('id', $returnId)->value('status'))->toBe('POSTED')
        ->and((float) DB::table('stock_movements')->where('reference_type', 'purchase_return')->where('reference_id', $returnId)->value('qty'))->toBe(2.0)
        ->and(DB::table('stock_movements')->where('reference_type', 'purchase_return')->where('reference_id', $returnId)->value('direction'))->toBe('out')
        ->and((float) DB::table('vendor_invoice_deductions')->where('purchase_return_id', $returnId)->value('amount'))->toBe(50000.0)
        ->and((float) DB::table('vendor_invoices')->where('id', $invoiceId)->value('outstanding_amount'))->toBe(200000.0);
});

it('rejects purchase return quantity above received balance', function () {
    post(route('apps.procurement.purchase-returns.store'), ['receiving_entry_id' => $this->receivingEntryId, 'return_date' => '2026-07-04', 'reason_category' => 'DAMAGED', 'lines' => [['receiving_entry_line_id' => $this->receivingEntryLineId, 'qty_returned' => 11, 'reason' => 'DAMAGED']]])
        ->assertSessionHasErrors('lines.0.qty_returned');
});
