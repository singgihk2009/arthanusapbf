<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use App\Services\Procurement\FacilityInheritanceService;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly FacilityInheritanceService $facilityInheritanceService)
    {
    }
    public function index(Request $request): Response
    {
        $query = GoodsReceipt::with(['purchaseOrder:id,po_number', 'vendor:id,name'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->purchase_order_id, fn ($q, $v) => $q->where('purchase_order_id', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('received_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('received_date', '<=', $v))
            ->latest();

        $goodsReceipts = $query->paginate(10)->through(function ($gr) {
            $gr->total_qty = $gr->items()->sum('received_qty');
            $gr->total_value = $gr->items()->sum('inventory_total_cost');
            return $gr;
        });

        return Inertia::render('Apps/Procurement/GoodsReceipts/Index', ['goodsReceipts' => $goodsReceipts]);
    }

    public function createFromPO(PurchaseOrder $purchaseOrder): Response
    {
        abort_if(in_array($purchaseOrder->status, ['cancelled', 'closed', 'fully_received']), 422, 'PO tidak valid untuk receiving.');
        $purchaseOrder->load(['vendor:id,name', 'items.product:id,name', 'items.uom:id,name']);

        $items = $purchaseOrder->items->filter(fn ($i) => (float)($i->remaining_qty ?? ($i->qty_ordered - $i->received_qty)) > 0 && ! $i->is_closed)
            ->values()->map(fn ($i) => [
                'purchase_order_item_id' => $i->id,
                'product_id' => $i->product_id,
                'product_name' => $i->product?->name,
                'ordered_qty' => $i->qty_ordered,
                'received_qty' => $i->received_qty,
                'remaining_qty' => $i->remaining_qty ?? ($i->qty_ordered - $i->received_qty),
                'uom' => $i->uom?->name,
                'uom_id' => $i->uom_id,
                'po_unit_price' => $i->unit_price,
                'suggested_received_qty' => $i->remaining_qty ?? ($i->qty_ordered - $i->received_qty),
            ]);

        $warehouses = DB::table('warehouses')
            ->select(['id', 'code', 'name'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Apps/Procurement/GoodsReceipts/CreateFromPO', [
            'purchaseOrder' => $purchaseOrder,
            'items' => $items,
            'warehouses' => $warehouses,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'received_date' => 'required|date',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
        ]);
        $po = PurchaseOrder::with('items')->findOrFail($data['purchase_order_id']);
        abort_if(in_array($po->status, ['cancelled', 'closed', 'fully_received']), 422, 'PO sudah ditutup/dibatalkan.');

        $gr = GoodsReceipt::create([
            'business_id' => 1, 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'warehouse_id' => $data['warehouse_id'],
            'gr_number' => $this->nextNumber(), 'received_date' => $data['received_date'], 'status' => 'draft', 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()?->id,
        ]);

        $stored = 0;
        foreach ($request->input('items', []) as $item) {
            $poi = PurchaseOrderItem::findOrFail($item['purchase_order_item_id']);
            if ((int)$poi->purchase_order_id !== (int)$po->id || (int)$poi->product_id !== (int)$item['product_id']) abort(422, 'Item tidak sesuai PO.');
            $remaining = (float)($poi->remaining_qty ?? ($poi->qty_ordered - $poi->received_qty));
            $receive = (float)($item['received_qty'] ?? 0);
            if ($receive <= 0) continue;
            abort_if($receive > $remaining, 422, 'Qty receive melebihi remaining qty.');
            $product = $poi->product()->first();
            if (($product?->is_expiry_tracked || $product?->requires_expiry_tracking) && empty($item['expired_date'])) abort(422, 'Expiry date wajib untuk produk expiry tracked.');
            if (($product?->is_batch_tracked || $product?->requires_batch_tracking) && empty($item['batch_number'])) abort(422, 'Batch number wajib untuk produk batch tracked.');

            $stored++;
            $gr->items()->create(array_merge([
                'purchase_order_item_id' => $poi->id, 'product_id' => $poi->product_id, 'warehouse_id' => $item['warehouse_id'] ?? $data['warehouse_id'],
                'ordered_qty' => $poi->qty_ordered, 'previously_received_qty' => $poi->received_qty, 'received_qty' => $receive,
                'remaining_qty' => $remaining - $receive, 'uom_id' => $poi->uom_id, 'po_unit_price' => $poi->unit_price,
                'inventory_unit_cost' => $poi->unit_price, 'inventory_total_cost' => $receive * (float)$poi->unit_price,
                'batch_number' => $item['batch_number'] ?? null, 'expired_date' => $item['expired_date'] ?? null,
                'condition_status' => $item['condition_status'] ?? 'good', 'notes' => $item['notes'] ?? null,
            ], $this->facilityInheritanceService->mapFromPoLine($poi)));
        }
        abort_if($stored === 0, 422, 'Minimal 1 item dengan qty > 0.');
        return redirect()->route('apps.procurement.goods-receipts.show', $gr->id)->with('success', 'Draft GR tersimpan.');
    }

    public function show(GoodsReceipt $goodsReceipt): Response
    {
        $goodsReceipt->load(['purchaseOrder:id,po_number', 'vendor:id,name', 'items.product:id,name']);
        return Inertia::render('Apps/Procurement/GoodsReceipts/Show', ['goodsReceipt' => $goodsReceipt]);
    }

    public function post(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_if($goodsReceipt->status !== 'draft', 422, 'Hanya draft yang bisa di-post.');
        DB::transaction(function () use ($goodsReceipt) {
            $gr = GoodsReceipt::with('items')->lockForUpdate()->findOrFail($goodsReceipt->id);
            abort_if($gr->status !== 'draft', 422, 'GR sudah diposting.');
            foreach ($gr->items as $item) {
                $poi = PurchaseOrderItem::lockForUpdate()->findOrFail($item->purchase_order_item_id);
                $remaining = (float)($poi->remaining_qty ?? ($poi->qty_ordered - $poi->received_qty));
                abort_if((float)$item->received_qty > $remaining, 422, 'Qty melebihi remaining terbaru.');
                $poi->received_qty += $item->received_qty;
                $poi->remaining_qty = max(0, (float)$poi->qty_ordered - (float)$poi->received_qty);
                $poi->save();

                StockMovement::create(['business_id' => $gr->business_id ?? 1, 'product_id' => $item->product_id, 'warehouse_id' => $item->warehouse_id ?? $gr->warehouse_id,
                    'reference_type' => 'goods_receipt', 'reference_id' => $gr->id, 'reference_item_id' => $item->id, 'movement_date' => $gr->received_date,
                    'direction' => 'in', 'qty' => $item->received_qty, 'unit_cost' => $item->po_unit_price, 'total_cost' => $item->received_qty * $item->po_unit_price,
                    'batch_number' => $item->batch_number, 'expired_date' => $item->expired_date, 'notes' => $item->notes, 'created_by' => auth()->id(),
                    'is_facility_item' => $item->is_facility_item, 'facility_type' => $item->facility_type, 'facility_document_id' => $item->facility_document_id,
                    'facility_reference_no' => $item->facility_reference_no, 'kek_classification' => $item->kek_classification, 'facility_status' => 'active', 'facility_notes' => $item->notes]);
            }
            $gr->update(['status' => 'posted', 'posted_at' => now()]);
            $po = PurchaseOrder::with('items')->findOrFail($gr->purchase_order_id);
            $total = $po->items->count();
            $done = $po->items->filter(fn ($i) => (float)($i->remaining_qty ?? 0) <= 0 || $i->is_closed)->count();
            $has = $po->items->contains(fn ($i) => (float)$i->received_qty > 0);
            $po->fulfillment_status = $done === $total && $total > 0 ? 'fully_received' : ($has ? 'partially_received' : 'open');
            $po->save();
        });

        return back()->with('success', 'GR berhasil diposting.');
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_if($goodsReceipt->status !== 'draft', 422, 'Hanya draft yang dapat dihapus. Reversal posted belum tersedia.');
        $goodsReceipt->delete();
        return redirect()->route('apps.procurement.goods-receipts.index')->with('success', 'Draft GR dihapus.');
    }

    private function nextNumber(): string
    {
        $prefix = 'GR-'.now()->format('Ym').'-';
        $last = GoodsReceipt::where('gr_number', 'like', $prefix.'%')->orderByDesc('gr_number')->value('gr_number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;
        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
