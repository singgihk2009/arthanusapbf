<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
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
    $this->poItemId = DB::table('purchase_order_items')->insertGetId(['purchase_order_id' => $this->poId, 'product_id' => $this->itemId, 'item_id' => $this->itemId, 'qty_ordered' => 10, 'received_qty' => 10, 'remaining_qty' => 0, 'uom_id' => $this->uomId, 'unit_price' => 25000, 'created_at' => now(), 'updated_at' => now()]);
    $this->goodsReceiptId = DB::table('goods_receipts')->insertGetId(['business_id' => 1, 'number' => 'GR-PRR-001', 'gr_number' => 'GR-PRR-001', 'purchase_order_id' => $this->poId, 'warehouse_id' => $this->warehouseId, 'supplier_id' => $this->supplierId, 'vendor_id' => $this->vendorId, 'document_date' => '2026-07-02', 'received_date' => '2026-07-02', 'status' => 'posted', 'created_at' => now(), 'updated_at' => now()]);
    $this->goodsReceiptItemId = DB::table('goods_receipt_items')->insertGetId(['goods_receipt_id' => $this->goodsReceiptId, 'purchase_order_item_id' => $this->poItemId, 'product_id' => $this->itemId, 'warehouse_id' => $this->warehouseId, 'ordered_qty' => 10, 'previously_received_qty' => 0, 'received_qty' => 10, 'remaining_qty' => 0, 'uom_id' => $this->uomId, 'po_unit_price' => 25000, 'inventory_unit_cost' => 25000, 'inventory_total_cost' => 250000, 'batch_number' => 'B-EXP', 'expired_date' => '2026-07-31', 'condition_status' => 'good', 'created_at' => now(), 'updated_at' => now()]);
});

it('posts purchase return, records stock out, and creates invoice deduction', function () {
    $invoiceId = DB::table('vendor_invoices')->insertGetId(['company_id' => 1, 'vendor_id' => $this->vendorId, 'invoice_no_internal' => 'VI-PRR-001', 'vendor_invoice_no' => 'SUP-PRR-001', 'invoice_date' => '2026-07-03', 'due_date' => '2026-08-03', 'currency_code' => 'IDR', 'exchange_rate' => 1, 'subtotal' => 250000, 'tax_amount' => 0, 'discount_amount' => 0, 'freight_amount' => 0, 'grand_total' => 250000, 'net_payable_amount' => 250000, 'paid_amount' => 0, 'outstanding_amount' => 250000, 'status' => 'POSTED', 'payment_status' => 'unpaid', 'created_at' => now(), 'updated_at' => now()]);

    post(route('apps.procurement.purchase-returns.store'), ['goods_receipt_id' => $this->goodsReceiptId, 'return_date' => '2026-07-04', 'reason_category' => 'EXPIRED', 'lines' => [['goods_receipt_item_id' => $this->goodsReceiptItemId, 'qty_returned' => 2, 'reason' => 'EXPIRED', 'condition_notes' => 'Expired saat inspeksi']]])->assertRedirect();

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
    post(route('apps.procurement.purchase-returns.store'), ['goods_receipt_id' => $this->goodsReceiptId, 'return_date' => '2026-07-04', 'reason_category' => 'DAMAGED', 'lines' => [['goods_receipt_item_id' => $this->goodsReceiptItemId, 'qty_returned' => 11, 'reason' => 'DAMAGED']]])
        ->assertSessionHasErrors('lines.0.qty_returned');
});
