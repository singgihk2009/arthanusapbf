<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\FacilityScheme;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\Vendor;
use App\Services\Inventory\FacilityReferenceValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly FacilityReferenceValidationService $facilityValidationService) {}
    public function index(Request $request)
    {
        $query = PurchaseOrder::with('vendor:id,name')
            ->withCount(['items as outstanding_items_count' => function ($itemQuery) {
                $itemQuery->whereRaw('COALESCE(remaining_qty, qty_ordered - COALESCE(received_qty, qty_received, 0)) > 0')
                    ->where(function ($lineQuery) {
                        $lineQuery->whereNull('is_closed')->orWhere('is_closed', false);
                    });
            }])
            ->latest('document_date');
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
            'facilitySchemes' => FacilityScheme::query()->where('is_active', true)->orderBy('code')->get(['id','code','name','is_restricted','requires_reference_no']),
            'defaultFacilitySchemeId' => FacilityScheme::query()->where('code', 'REGULAR')->value('id'),
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
                'fulfillment_status' => 'not_received',
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);
            $defaultFacilitySchemeId = (int) (FacilityScheme::query()->where('code', 'REGULAR')->value('id') ?? 0);
            foreach ($data['items'] as $item) {
                $this->facilityValidationService->validateFacilityReference(
                    (int) ($item['facility_scheme_id'] ?? $data['facility_scheme_id'] ?? $defaultFacilitySchemeId),
                    $item['facility_reference_no'] ?? null
                );
                $lineBase = (float)$item['qty_ordered'] * (float)$item['unit_price'];
                $payload = array_merge($item, [
                    'line_total' => $lineBase - (float) ($item['discount_amount'] ?? 0) + (float) ($item['tax_amount'] ?? 0),
                ]);

                $payload['facility_scheme_id'] = $item['facility_scheme_id'] ?? $data['facility_scheme_id'] ?? $defaultFacilitySchemeId ?: null;

                $po->items()->create($this->sanitizePurchaseOrderItemPayload($payload));
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

        $goodsReceipts = GoodsReceipt::query()
            ->where(function ($query) use ($purchaseOrder) {
                $query->where('purchase_order_id', $purchaseOrder->id)
                    ->orWhere('po_id', $purchaseOrder->id);
            })
            ->latest('received_date')
            ->get();

        if ($goodsReceipts->isEmpty()) {
            $receivingEntries = DB::table('receiving_entries')
                ->where(function ($query) use ($purchaseOrder) {
                    $query->where(function ($q) use ($purchaseOrder) {
                        $q->where('source_type', 'purchase_order')
                            ->where('source_id', $purchaseOrder->id);
                    })->orWhere('reference', $purchaseOrder->po_number);
                })
                ->latest('transaction_date')
                ->get()
                ->map(function (object $entry): object {
                    $lineForeignKey = Schema::hasColumn('receiving_entry_lines', 'receiving_entry_id')
                        ? 'receiving_entry_id'
                        : (Schema::hasColumn('receiving_entry_lines', 'entry_id') ? 'entry_id' : 'receiving_id');

                    $totals = DB::table('receiving_entry_lines')
                        ->where($lineForeignKey, $entry->id)
                        ->selectRaw('COALESCE(SUM(qty), 0) as total_qty')
                        ->selectRaw('COALESCE(SUM(qty * price), 0) as total_value')
                        ->first();

                    $entry->gr_number = $entry->number ?? null;
                    $entry->received_date = $entry->transaction_date ?? null;
                    $entry->status = $entry->status ?? 'posted';
                    $entry->total_qty = (float) ($totals->total_qty ?? 0);
                    $entry->total_value = (float) ($totals->total_value ?? 0);

                    return $entry;
                });

            $goodsReceipts = $receivingEntries;
        }

        $purchaseOrder->setRelation('goodsReceipts', $goodsReceipts);

        $purchaseOrder->goodsReceipts->each(function ($gr) {
            if ($gr instanceof GoodsReceipt) {
                $gr->total_qty = $gr->items()->sum('received_qty');
                $gr->total_value = $gr->items()->sum('inventory_total_cost');
            }
        });
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
            'facilitySchemes' => FacilityScheme::query()->where('is_active', true)->orderBy('code')->get(['id','code','name','is_restricted','requires_reference_no']),
            'defaultFacilitySchemeId' => FacilityScheme::query()->where('code', 'REGULAR')->value('id'),
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
            $defaultFacilitySchemeId = (int) (FacilityScheme::query()->where('code', 'REGULAR')->value('id') ?? 0);
            foreach ($data['items'] as $item) {
                $this->facilityValidationService->validateFacilityReference(
                    (int) ($item['facility_scheme_id'] ?? $data['facility_scheme_id'] ?? $defaultFacilitySchemeId),
                    $item['facility_reference_no'] ?? null
                );
                $lineBase = (float)$item['qty_ordered'] * (float)$item['unit_price'];
                $payload = array_merge($item, [
                    'line_total' => $lineBase - (float) ($item['discount_amount'] ?? 0) + (float) ($item['tax_amount'] ?? 0),
                ]);

                $payload['facility_scheme_id'] = $item['facility_scheme_id'] ?? $data['facility_scheme_id'] ?? $defaultFacilitySchemeId ?: null;

                $purchaseOrder->items()->create($this->sanitizePurchaseOrderItemPayload($payload));
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
            'facility_scheme_id' => ['nullable','exists:facility_schemes,id'],
            'items.*.facility_scheme_id' => ['nullable','exists:facility_schemes,id'],
            'items.*.facility_reference_no' => ['nullable','string','max:120'],
            'items.*.facility_reference_date' => ['nullable','date'],
            'items.*.facility_reference_note' => ['nullable','string'],
            'items.*.notes' => ['nullable','string'],
        ]);
    }


    private function sanitizePurchaseOrderItemPayload(array $payload): array
    {
        $allowedColumns = $this->purchaseOrderItemColumns();

        if (empty($allowedColumns)) {
            return $payload;
        }

        return array_intersect_key($payload, array_flip($allowedColumns));
    }

    /**
     * Backward-compatible helper kept to avoid fatal errors in environments
     * that still call the old method name (e.g. cached container/opcache).
     */
    private function purchaseOrderItemHasFacilitySchemeColumn(): bool
    {
        return in_array('facility_scheme_id', $this->purchaseOrderItemColumns(), true);
    }

    private function purchaseOrderItemColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        if (! Schema::hasTable('purchase_order_items')) {
            $columns = [];

            return $columns;
        }

        $columns = Schema::getColumnListing('purchase_order_items');

        return $columns;
    }

    private function generateNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $last = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', $prefix.'%')
            ->orderByDesc('po_number')
            ->value('po_number');

        $seq = $last ? ((int) substr($last, -4) + 1) : 1;

        do {
            $number = $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $exists = PurchaseOrder::withTrashed()->where('po_number', $number)->exists();
            $seq++;
        } while ($exists);

        return $number;
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
