<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('vendor:id,name')->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', fn($v) => $v->where('name', 'like', "%{$search}%"));
            });
        }

        return Inertia::render('Apps/Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Apps/Procurement/PurchaseOrders/Create', [
            'vendors' => Vendor::select('id','name')->where('qualification_status', 'qualified')->orderBy('name')->get(),
            'products' => Item::select('id','name','base_uom_id')->orderBy('name')->get(),
            'uoms' => Uom::select('id','name')->orderBy('name')->get(),
            'defaultVendorId' => $request->integer('vendor_id') ?: null,
            'returnTo' => $request->string('return_to')->toString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $poDate = Carbon::parse($data['po_date'])->toDateString();
        $expectedDeliveryDate = !empty($data['expected_delivery_date'])
            ? Carbon::parse($data['expected_delivery_date'])->toDateString()
            : null;

        DB::transaction(function () use ($data, $request, $poDate, $expectedDeliveryDate) {
            $poNumber = $this->generateNumber();
            $warehouseId = Warehouse::query()->value('id');
            $supplierId = $this->resolveSupplierId((int) $data['vendor_id']);

            $po = PurchaseOrder::create([
                'number' => $poNumber,
                'po_number' => $poNumber,
                'vendor_id' => $data['vendor_id'],
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'document_date' => $poDate,
                'po_date' => $poDate,
                'expected_date' => $expectedDeliveryDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
            foreach ($data['items'] as $item) {
                $lineBase = (float)$item['qty_ordered'] * (float)$item['unit_price'];
                $po->items()->create(array_merge($item, ['line_total' => $lineBase - (float)$item['discount_amount'] + (float)$item['tax_amount']]));
            }
            $po->recalculateTotals();
        });
        $returnTo = (string) $request->input('return_to', '');
        if ($returnTo !== '') {
            return redirect($returnTo)->with('success', 'Purchase Order dibuat.');
        }
        return to_route('apps.procurement.purchase-orders.index')->with('success', 'Purchase Order dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['vendor:id,name','items.product:id,name','items.uom:id,name']);
        return Inertia::render('Apps/Procurement/PurchaseOrders/Show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 422, 'PO hanya dapat diubah ketika draft.');
        $purchaseOrder->load('items');
        return Inertia::render('Apps/Procurement/PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'vendors' => Vendor::select('id','name')->where('qualification_status', 'qualified')->orderBy('name')->get(),
            'products' => Item::select('id','name','base_uom_id')->orderBy('name')->get(),
            'uoms' => Uom::select('id','name')->orderBy('name')->get(),
            'returnTo' => $request->string('return_to')->toString(),
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->isEditable(), 422, 'PO hanya dapat diubah ketika draft.');
        $data = $this->validateData($request);
        $poDate = Carbon::parse($data['po_date'])->toDateString();
        $expectedDeliveryDate = !empty($data['expected_delivery_date'])
            ? Carbon::parse($data['expected_delivery_date'])->toDateString()
            : null;

        DB::transaction(function () use ($purchaseOrder, $data, $poDate, $expectedDeliveryDate) {
            $supplierId = $this->resolveSupplierId((int) $data['vendor_id']);
            $purchaseOrder->update([
                'vendor_id' => $data['vendor_id'],
                'supplier_id' => $supplierId,
                'po_date' => $poDate,
                'document_date' => $poDate,
                'expected_delivery_date' => $expectedDeliveryDate,
                'expected_date' => $expectedDeliveryDate,
                'notes' => $data['notes'] ?? null,
            ]);
            $purchaseOrder->items()->delete();
            foreach ($data['items'] as $item) {
                $lineBase = (float)$item['qty_ordered'] * (float)$item['unit_price'];
                $purchaseOrder->items()->create(array_merge($item, ['line_total' => $lineBase - (float)$item['discount_amount'] + (float)$item['tax_amount']]));
            }
            $purchaseOrder->recalculateTotals();
        });
        $returnTo = (string) $request->input('return_to', '');
        if ($returnTo !== '') {
            return redirect($returnTo)->with('success', 'Purchase Order diupdate.');
        }
        return to_route('apps.procurement.purchase-orders.show', $purchaseOrder);
    }

    public function approve(PurchaseOrder $purchaseOrder, Request $request){ $purchaseOrder->approve($request->user()?->id); return back()->with('success','PO di-approve.'); }
    public function cancel(PurchaseOrder $purchaseOrder){ $purchaseOrder->cancel(); return back()->with('success','PO dibatalkan.'); }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        abort_unless(in_array(strtolower((string) $purchaseOrder->status), ['draft', 'cancelled'], true), 422, 'Hanya draft/cancelled yang boleh dihapus.');
        $purchaseOrder->delete();
        return to_route('apps.procurement.purchase-orders.index');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'vendor_id' => ['required','exists:vendors,id'],
            'po_date' => ['required','date'],
            'expected_delivery_date' => ['nullable','date'],
            'notes' => ['nullable','string'],
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['nullable','exists:items,id'],
            'items.*.product_name' => ['nullable','string'],
            'items.*.uom_id' => ['nullable','exists:uoms,id'],
            'items.*.qty_ordered' => ['required','numeric','gt:0'],
            'items.*.unit_price' => ['required','numeric','min:0'],
            'items.*.discount_amount' => ['nullable','numeric','min:0'],
            'items.*.tax_amount' => ['nullable','numeric','min:0'],
            'items.*.notes' => ['nullable','string'],
        ]);
    }

    private function generateNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $last = PurchaseOrder::where('po_number','like',$prefix.'%')->orderByDesc('po_number')->value('po_number');
        $seq = $last ? ((int)substr($last, -4) + 1) : 1;
        return $prefix.str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    private function resolveSupplierId(int $vendorId): int
    {
        $vendor = Vendor::query()->find($vendorId);
        abort_if(!$vendor, 422, 'Vendor tidak ditemukan.');

        $vendorCode = $vendor->vendor_code;
        $supplierId = DB::table('suppliers')->where('code', $vendorCode)->value('id');

        // Backward compatibility: after vendor migration, several datasets keep
        // supplier rows keyed directly by vendor id and no longer by code.
        if (!$supplierId) {
            $supplierId = DB::table('suppliers')->where('id', $vendorId)->value('id');
        }

        if (!$supplierId) {
            $supplierId = DB::table('suppliers')->insertGetId([
                'code' => $vendorCode ?: ('VENDOR-'.$vendor->id),
                'name' => $vendor->vendor_name ?: $vendor->name ?: ('Vendor #'.$vendor->id),
                'phone' => $vendor->phone,
                'email' => $vendor->email,
                'address' => $vendor->address,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        abort_if(!$supplierId, 422, 'Supplier untuk vendor terpilih tidak ditemukan.');

        return (int) $supplierId;
    }
}
