<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockMovement;
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
        $purchaseReturns = DB::table('purchase_returns as pr')
            ->leftJoin('vendors as v', 'v.id', '=', 'pr.vendor_id')
            ->leftJoin('receiving_entries as re', 're.id', '=', 'pr.receiving_entry_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pr.warehouse_id')
            ->whereNull('pr.deleted_at')
            ->when($request->status, fn ($q, $v) => $q->where('pr.status', $v))
            ->when($request->vendor_id, fn ($q, $v) => $q->where('pr.vendor_id', $v))
            ->when($request->reason, fn ($q, $v) => $q->where('pr.reason_category', $v))
            ->when($request->date_from, fn ($q, $v) => $q->whereDate('pr.return_date', '>=', $v))
            ->when($request->date_to, fn ($q, $v) => $q->whereDate('pr.return_date', '<=', $v))
            ->select([
                'pr.*',
                DB::raw('COALESCE(v.vendor_name, v.name, pr.vendor_id) as vendor_label'),
                DB::raw('COALESCE(re.number, re.reference) as receiving_number'),
                DB::raw('COALESCE(w.code, w.name, pr.warehouse_id) as warehouse_label'),
            ])
            ->orderByDesc('pr.id')
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
        $receivingEntries = DB::table('receiving_entries as re')
            ->leftJoin('vendors as v', 'v.id', '=', 're.vendor_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 're.warehouse_id')
            ->where('re.source_type', 'purchase_order')
            ->where('re.transaction_code', 'PEMBELIAN')
            ->whereRaw('LOWER(COALESCE(re.status, ?)) = ?', ['draft', 'posted'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('receiving_entry_lines as rel')
                    ->whereColumn('rel.receiving_entry_id', 're.id');
            })
            ->orderByDesc('re.transaction_date')
            ->orderByDesc('re.id')
            ->limit(200)
            ->get([
                're.id',
                're.number',
                're.reference',
                're.vendor_id',
                're.vendor_name',
                're.warehouse_id',
                're.transaction_date',
                're.status',
                DB::raw('COALESCE(v.vendor_name, v.name, re.vendor_name) as vendor_label'),
                DB::raw('COALESCE(w.code, w.name, re.warehouse_id) as warehouse_label'),
            ]);

        $selectedReceivingEntry = null;
        $lines = [];
        if ($request->integer('receiving_entry_id')) {
            $selectedReceivingEntry = $this->findReceivingEntry($request->integer('receiving_entry_id'));
            $lines = $this->returnableLines((int) $selectedReceivingEntry->id);
        }

        return Inertia::render('Apps/Procurement/PurchaseReturns/Form', [
            'receivingEntries' => $receivingEntries,
            'selectedReceivingEntry' => $selectedReceivingEntry,
            'lines' => $lines,
            'reasons' => self::REASONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $purchaseReturn = DB::transaction(function () use ($data, $request): PurchaseReturn {
            $receivingEntry = DB::table('receiving_entries')->lockForUpdate()->where('id', $data['receiving_entry_id'])->first();
            if (! $receivingEntry || strtolower((string) $receivingEntry->status) !== 'posted') {
                throw ValidationException::withMessages(['receiving_entry_id' => 'Receiving Entry harus sudah posted.']);
            }

            $selectedLines = collect($data['lines'])->filter(fn ($line) => (float) ($line['qty_returned'] ?? 0) > 0)->values();
            if ($selectedLines->isEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Minimal 1 line dengan qty return > 0.']);
            }

            $return = PurchaseReturn::create([
                'return_no' => $this->nextNumber(),
                'return_date' => $data['return_date'],
                'vendor_id' => $receivingEntry->vendor_id,
                'receiving_entry_id' => $receivingEntry->id,
                'warehouse_id' => $receivingEntry->warehouse_id,
                'status' => 'DRAFT',
                'reason_category' => $data['reason_category'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            $totalQty = 0.0;
            $totalAmount = 0.0;
            foreach ($selectedLines as $index => $line) {
                $receivingLine = DB::table('receiving_entry_lines')->lockForUpdate()->where('id', $line['receiving_entry_line_id'])->first();
                if (! $receivingLine || (int) $receivingLine->receiving_entry_id !== (int) $receivingEntry->id) {
                    throw ValidationException::withMessages(["lines.{$index}.receiving_entry_line_id" => 'Line tidak sesuai Receiving Entry.']);
                }
                $qty = (float) $line['qty_returned'];
                $available = $this->availableQty((int) $receivingLine->id);
                if ($qty > $available + 0.0001) {
                    throw ValidationException::withMessages(["lines.{$index}.qty_returned" => 'Qty return melebihi qty tersedia.']);
                }
                if (($line['reason'] ?? $data['reason_category']) === 'EXPIRED' && empty($receivingLine->expired_date)) {
                    throw ValidationException::withMessages(["lines.{$index}.reason" => 'Retur expired membutuhkan expired date dari line penerimaan.']);
                }
                $unitCost = (float) ($receivingLine->price ?? $receivingLine->inventory_unit_cost ?? 0);
                $lineAmount = $qty * $unitCost;
                $totalQty += $qty;
                $totalAmount += $lineAmount;
                $return->lines()->create([
                    'receiving_entry_line_id' => $receivingLine->id,
                    'item_id' => $receivingLine->item_id,
                    'warehouse_id' => $receivingEntry->warehouse_id,
                    'batch_number' => $receivingLine->batch_number ?? null,
                    'expired_date' => $receivingLine->expired_date ?? null,
                    'qty_returned' => $qty,
                    'uom_id' => $receivingLine->uom_id,
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
        $header = DB::table('purchase_returns as pr')
            ->leftJoin('vendors as v', 'v.id', '=', 'pr.vendor_id')
            ->leftJoin('receiving_entries as re', 're.id', '=', 'pr.receiving_entry_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pr.warehouse_id')
            ->where('pr.id', $purchaseReturn->id)
            ->whereNull('pr.deleted_at')
            ->select([
                'pr.*',
                DB::raw('COALESCE(v.vendor_name, v.name, pr.vendor_id) as vendor_label'),
                DB::raw('COALESCE(re.number, re.reference) as receiving_number'),
                DB::raw('COALESCE(w.code, w.name, pr.warehouse_id) as warehouse_label'),
            ])
            ->first();
        abort_unless($header, 404);

        $header->lines = DB::table('purchase_return_lines as prl')
            ->join('items as i', 'i.id', '=', 'prl.item_id')
            ->where('prl.purchase_return_id', $purchaseReturn->id)
            ->select('prl.*', DB::raw('COALESCE(i.name, i.sku) as item_name'))
            ->get();
        $header->deduction = DB::table('vendor_invoice_deductions as vid')
            ->leftJoin('vendor_invoices as vi', 'vi.id', '=', 'vid.vendor_invoice_id')
            ->where('vid.purchase_return_id', $purchaseReturn->id)
            ->select('vid.*', DB::raw('COALESCE(vi.invoice_no_internal, vi.vendor_invoice_no) as invoice_number'))
            ->first();

        return Inertia::render('Apps/Procurement/PurchaseReturns/Show', ['purchaseReturn' => $header]);
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
            $return = PurchaseReturn::with('lines')->lockForUpdate()->findOrFail($purchaseReturn->id);
            if ($return->status !== 'APPROVED') {
                throw ValidationException::withMessages(['status' => 'Purchase Return harus APPROVED sebelum posting.']);
            }

            foreach ($return->lines as $line) {
                $available = $this->availableQty((int) $line->receiving_entry_line_id, (int) $line->id);
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
            $this->createDeduction($return->fresh());
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
            'receiving_entry_id' => ['required', 'exists:receiving_entries,id'],
            'return_date' => ['required', 'date'],
            'reason_category' => ['required', Rule::in(self::REASONS)],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.receiving_entry_line_id' => ['required', 'exists:receiving_entry_lines,id'],
            'lines.*.qty_returned' => ['nullable', 'numeric', 'min:0'],
            'lines.*.reason' => ['nullable', Rule::in(self::REASONS)],
            'lines.*.condition_notes' => ['nullable', 'string'],
        ]);
    }

    private function findReceivingEntry(int $id): object
    {
        $entry = DB::table('receiving_entries as re')
            ->leftJoin('vendors as v', 'v.id', '=', 're.vendor_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 're.warehouse_id')
            ->where('re.id', $id)
            ->select([
                're.*',
                DB::raw('COALESCE(v.vendor_name, v.name, re.vendor_name) as vendor_label'),
                DB::raw('COALESCE(w.code, w.name, re.warehouse_id) as warehouse_label'),
            ])
            ->first();

        abort_unless($entry, 404);
        if (strtolower((string) $entry->status) !== 'posted') {
            throw ValidationException::withMessages(['receiving_entry_id' => 'Receiving Entry harus sudah posted.']);
        }

        return $entry;
    }

    private function returnableLines(int $receivingEntryId): array
    {
        return DB::table('receiving_entry_lines as rel')
            ->join('items as i', 'i.id', '=', 'rel.item_id')
            ->where('rel.receiving_entry_id', $receivingEntryId)
            ->select('rel.*', DB::raw('COALESCE(i.name, i.sku) as item_name'))
            ->get()
            ->map(function (object $line): array {
                $available = $this->availableQty((int) $line->id);

                return [
                    'receiving_entry_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item_name,
                    'batch_number' => $line->batch_number ?? null,
                    'expired_date' => $line->expired_date ?? null,
                    'qty_received' => (float) $line->qty,
                    'qty_available_to_return' => $available,
                    'uom_id' => $line->uom_id,
                    'unit_cost' => (float) ($line->price ?? $line->inventory_unit_cost ?? 0),
                ];
            })
            ->filter(fn ($line) => $line['qty_available_to_return'] > 0.0001)
            ->values()
            ->all();
    }

    private function availableQty(int $receivingEntryLineId, ?int $ignoreReturnLineId = null): float
    {
        $received = (float) DB::table('receiving_entry_lines')->where('id', $receivingEntryLineId)->value('qty');
        $returned = (float) DB::table('purchase_return_lines as prl')
            ->join('purchase_returns as pr', 'pr.id', '=', 'prl.purchase_return_id')
            ->where('prl.receiving_entry_line_id', $receivingEntryLineId)
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
