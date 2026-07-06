<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
use App\Models\Procurement\GoodsReceipt;
use App\Models\Procurement\GoodsReceiptItem;
use App\Models\Procurement\PurchaseReturn;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorInvoiceDeduction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseReturnController extends Controller
{
    private const REASONS = ['DAMAGED', 'EXPIRED', 'NEAR_EXPIRED', 'WRONG_ITEM', 'WRONG_BATCH', 'WRONG_QTY', 'QUALITY_REJECTED', 'RECALL', 'OTHER'];

    public function index(Request $request): Response
    {
        $purchaseReturns = PurchaseReturn::query()
            ->with(['vendor:id,name,vendor_name', 'goodsReceipt:id,number,gr_number', 'warehouse:id,code,name'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('vendor_id', $v))
            ->when($request->reason, fn ($q, $v) => $q->where('reason_category', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('return_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('return_date', '<=', $v))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Apps/Procurement/PurchaseReturns/Index', [
            'purchaseReturns' => $purchaseReturns,
            'filters' => $request->only(['status', 'vendor_id', 'reason', 'date_from', 'date_to']),
            'reasons' => self::REASONS,
        ]);
    }

    public function create(Request $request): Response
    {
        $goodsReceipts = GoodsReceipt::query()
            ->with(['vendor:id,name,vendor_name', 'warehouse:id,code,name'])
            ->where('status', 'posted')
            ->latest('id')
            ->limit(100)
            ->get(['id', 'number', 'gr_number', 'vendor_id', 'warehouse_id', 'received_date', 'status']);

        $selectedGoodsReceipt = null;
        $lines = [];
        if ($request->integer('goods_receipt_id')) {
            $selectedGoodsReceipt = GoodsReceipt::with(['vendor:id,name,vendor_name', 'warehouse:id,code,name'])->findOrFail($request->integer('goods_receipt_id'));
            $lines = $this->returnableLines($selectedGoodsReceipt);
        }

        return Inertia::render('Apps/Procurement/PurchaseReturns/Form', [
            'goodsReceipts' => $goodsReceipts,
            'selectedGoodsReceipt' => $selectedGoodsReceipt,
            'lines' => $lines,
            'reasons' => self::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $purchaseReturn = DB::transaction(function () use ($data, $request): PurchaseReturn {
            $goodsReceipt = GoodsReceipt::with('items')->lockForUpdate()->findOrFail($data['goods_receipt_id']);
            if (strtolower((string) $goodsReceipt->status) !== 'posted') {
                throw ValidationException::withMessages(['goods_receipt_id' => 'Goods Receipt harus sudah posted.']);
            }

            $selectedLines = collect($data['lines'])->filter(fn ($line) => (float) ($line['qty_returned'] ?? 0) > 0)->values();
            if ($selectedLines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Minimal 1 line dengan qty return > 0.']);
            }

            $return = PurchaseReturn::create([
                'return_no' => $this->nextNumber(),
                'return_date' => $data['return_date'],
                'vendor_id' => $goodsReceipt->vendor_id,
                'goods_receipt_id' => $goodsReceipt->id,
                'warehouse_id' => $goodsReceipt->warehouse_id,
                'status' => 'DRAFT',
                'reason_category' => $data['reason_category'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $totalQty = 0.0;
            $totalAmount = 0.0;
            foreach ($selectedLines as $index => $line) {
                $grLine = GoodsReceiptItem::lockForUpdate()->findOrFail($line['goods_receipt_item_id']);
                if ((int) $grLine->goods_receipt_id !== (int) $goodsReceipt->id) {
                    throw ValidationException::withMessages(["lines.{$index}.goods_receipt_item_id" => 'Line tidak sesuai Goods Receipt.']);
                }
                $qty = (float) $line['qty_returned'];
                $available = $this->availableQty($grLine->id);
                if ($qty > $available + 0.0001) {
                    throw ValidationException::withMessages(["lines.{$index}.qty_returned" => 'Qty return melebihi qty tersedia.']);
                }
                if (($line['reason'] ?? $data['reason_category']) === 'EXPIRED' && empty($grLine->expired_date)) {
                    throw ValidationException::withMessages(["lines.{$index}.reason" => 'Retur expired membutuhkan expired date dari line penerimaan.']);
                }
                $unitCost = (float) ($grLine->po_unit_price ?? $grLine->inventory_unit_cost ?? 0);
                $lineAmount = $qty * $unitCost;
                $totalQty += $qty;
                $totalAmount += $lineAmount;
                $return->lines()->create([
                    'goods_receipt_item_id' => $grLine->id,
                    'item_id' => $grLine->product_id,
                    'warehouse_id' => $grLine->warehouse_id ?: $goodsReceipt->warehouse_id,
                    'batch_number' => $grLine->batch_number,
                    'expired_date' => $grLine->expired_date,
                    'qty_returned' => $qty,
                    'uom_id' => $grLine->uom_id,
                    'unit_cost' => $unitCost,
                    'line_amount' => $lineAmount,
                    'reason' => $line['reason'] ?? $data['reason_category'],
                    'condition_notes' => $line['condition_notes'] ?? null,
                ]);
            }

            $return->update(['total_qty' => $totalQty, 'total_amount' => $totalAmount]);

            return $return;
        });

        return redirect()->route('apps.procurement.purchase-returns.show', $purchaseReturn)->with('success', 'Draft Purchase Return tersimpan.');
    }

    public function show(PurchaseReturn $purchaseReturn): Response
    {
        $purchaseReturn->load(['vendor:id,name,vendor_name', 'goodsReceipt:id,number,gr_number', 'warehouse:id,code,name', 'lines.item:id,name,code', 'lines.goodsReceiptItem:id,received_qty,po_unit_price,batch_number,expired_date', 'deduction.vendorInvoice:id,invoice_no_internal,vendor_invoice_no']);

        return Inertia::render('Apps/Procurement/PurchaseReturns/Show', ['purchaseReturn' => $purchaseReturn]);
    }

    public function submit(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        if ($purchaseReturn->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang bisa di-submit.']);
        }
        $purchaseReturn->update(['status' => 'SUBMITTED']);

        return back()->with('success', 'Purchase Return diajukan untuk approval.');
    }

    public function approve(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        if (! in_array($purchaseReturn->status, ['DRAFT', 'SUBMITTED'], true)) {
            throw ValidationException::withMessages(['status' => 'Status tidak valid untuk approval.']);
        }
        $purchaseReturn->update(['status' => 'APPROVED', 'approved_by' => auth()->id(), 'approved_at' => now()]);

        return back()->with('success', 'Purchase Return disetujui.');
    }

    public function post(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        DB::transaction(function () use ($purchaseReturn): void {
            $return = PurchaseReturn::with(['lines', 'goodsReceipt'])->lockForUpdate()->findOrFail($purchaseReturn->id);
            if ($return->status !== 'APPROVED') {
                throw ValidationException::withMessages(['status' => 'Purchase Return harus APPROVED sebelum posting.']);
            }

            foreach ($return->lines as $line) {
                $available = $this->availableQty((int) $line->goods_receipt_item_id, (int) $line->id);
                if ((float) $line->qty_returned > $available + 0.0001) {
                    throw ValidationException::withMessages(['lines' => 'Qty return melebihi qty tersedia terbaru.']);
                }

                StockMovement::create([
                    'business_id' => 1,
                    'product_id' => $line->item_id,
                    'warehouse_id' => $line->warehouse_id,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $return->id,
                    'reference_item_id' => $line->id,
                    'movement_date' => $return->return_date,
                    'direction' => 'out',
                    'qty' => $line->qty_returned,
                    'unit_cost' => $line->unit_cost,
                    'total_cost' => $line->line_amount,
                    'batch_number' => $line->batch_number,
                    'expired_date' => $line->expired_date,
                    'notes' => $line->condition_notes,
                    'created_by' => auth()->id(),
                ]);
            }

            $return->update(['status' => 'POSTED', 'posted_by' => auth()->id(), 'posted_at' => now()]);
            $this->createDeduction($return->fresh('goodsReceipt'));
        });

        return back()->with('success', 'Purchase Return diposting, stok keluar, dan potongan tagihan vendor dibuat.');
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        if ($purchaseReturn->status !== 'DRAFT') {
            throw ValidationException::withMessages(['status' => 'Hanya draft yang bisa dihapus.']);
        }
        $purchaseReturn->delete();

        return redirect()->route('apps.procurement.purchase-returns.index')->with('success', 'Draft Purchase Return dihapus.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'goods_receipt_id' => ['required', 'exists:goods_receipts,id'],
            'return_date' => ['required', 'date'],
            'reason_category' => ['required', Rule::in(self::REASONS)],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_item_id' => ['required', 'exists:goods_receipt_items,id'],
            'lines.*.qty_returned' => ['nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', Rule::in(self::REASONS)],
            'lines.*.condition_notes' => ['nullable', 'string'],
        ]);
    }

    private function returnableLines(GoodsReceipt $goodsReceipt): array
    {
        return $goodsReceipt->items()->with('product:id,name,code')->get()->map(function (GoodsReceiptItem $line): array {
            $available = $this->availableQty($line->id);

            return [
                'goods_receipt_item_id' => $line->id,
                'item_id' => $line->product_id,
                'item_name' => $line->product?->name,
                'batch_number' => $line->batch_number,
                'expired_date' => $line->expired_date,
                'qty_received' => (float) $line->received_qty,
                'qty_available_to_return' => $available,
                'uom_id' => $line->uom_id,
                'unit_cost' => (float) ($line->po_unit_price ?? $line->inventory_unit_cost ?? 0),
            ];
        })->filter(fn ($line) => $line['qty_available_to_return'] > 0.0001)->values()->all();
    }

    private function availableQty(int $goodsReceiptItemId, ?int $ignoreReturnLineId = null): float
    {
        $received = (float) GoodsReceiptItem::whereKey($goodsReceiptItemId)->value('received_qty');
        $returned = (float) DB::table('purchase_return_lines as prl')
            ->join('purchase_returns as pr', 'pr.id', '=', 'prl.purchase_return_id')
            ->where('prl.goods_receipt_item_id', $goodsReceiptItemId)
            ->whereNull('pr.deleted_at')
            ->whereNotIn('pr.status', ['CANCELLED', 'VOID'])
            ->when($ignoreReturnLineId, fn ($q) => $q->where('prl.id', '!=', $ignoreReturnLineId))
            ->sum('prl.qty_returned');

        return max(0, $received - $returned);
    }

    private function createDeduction(PurchaseReturn $return): void
    {
        $invoice = VendorInvoice::query()
            ->where('vendor_id', $return->vendor_id)
            ->whereIn('status', ['POSTED', 'PARTIAL_PAID'])
            ->where('outstanding_amount', '>', 0)
            ->oldest('invoice_date')
            ->lockForUpdate()
            ->first();

        $amount = (float) $return->total_amount;
        $applied = 0.0;
        if ($invoice) {
            $applied = min((float) $invoice->outstanding_amount, $amount);
            $invoice->outstanding_amount = max(0, (float) $invoice->outstanding_amount - $applied);
            $invoice->payment_status = $invoice->outstanding_amount <= 0 ? 'paid' : 'partial_paid';
            $invoice->status = $invoice->outstanding_amount <= 0 ? 'PAID' : 'PARTIAL_PAID';
            $invoice->save();
        }

        VendorInvoiceDeduction::create([
            'vendor_id' => $return->vendor_id,
            'vendor_invoice_id' => $invoice?->id,
            'purchase_return_id' => $return->id,
            'deduction_no' => 'VID-'.$return->return_no,
            'deduction_date' => $return->return_date,
            'amount' => $amount,
            'applied_amount' => $applied,
            'remaining_amount' => max(0, $amount - $applied),
            'status' => $amount - $applied <= 0.0001 ? 'APPLIED' : 'OPEN',
            'notes' => 'Potongan tagihan dari Purchase Return '.$return->return_no,
        ]);
    }

    private function nextNumber(): string
    {
        $prefix = 'PRR-'.now()->format('Ym').'-';
        $last = PurchaseReturn::withTrashed()->where('return_no', 'like', $prefix.'%')->orderByDesc('return_no')->value('return_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
