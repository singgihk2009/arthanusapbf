<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerInvoiceController extends Controller
{
    public function index()
    {
        $invoices = Schema::hasTable('customer_invoices')
            ? DB::table('customer_invoices as ci')
                ->leftJoin('customers as c', 'c.id', '=', 'ci.customer_id')
                ->orderByDesc('ci.id')
                ->paginate(15, [
                    'ci.id',
                    'ci.number',
                    'ci.invoice_date',
                    'ci.due_date',
                    'ci.status',
                    'ci.subtotal',
                    'ci.discount_total',
                    'ci.tax_total',
                    DB::raw(Schema::hasColumn('customer_invoices', 'freight_amount') ? 'ci.freight_amount' : '0 as freight_amount'),
                    'ci.grand_total',
                    'ci.balance_due',
                    DB::raw('COALESCE(c.customer_name, "-") as customer_name'),
                ])
            : collect();

        return Inertia::render('Apps/Sales/CustomerInvoices/Index', ['invoices' => $invoices]);
    }

    public function create(Request $request)
    {
        $dispatchIds = $this->parseDispatchIds($request->query('dispatch_ids', $request->query('dispatch_id')));

        return Inertia::render('Apps/Sales/CustomerInvoices/Form', [
            'invoiceDraft' => $dispatchIds ? $this->buildDraftFromDispatches($dispatchIds) : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'dispatch_ids' => ['required', 'array', 'min:1'],
            'dispatch_ids.*' => ['integer'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'tax_enabled' => ['nullable', 'boolean'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'freight_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $invoice = DB::transaction(function () use ($validated) {
            $draft = $this->buildDraftFromDispatches($validated['dispatch_ids'], (int) $validated['customer_id'], true);
            $lines = collect($draft['lines'] ?? []);

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages(['dispatch_ids' => 'Dispatch terpilih tidak memiliki line yang bisa ditagihkan.']);
            }

            $subtotal = (float) $lines->sum('line_total');
            $freightAmount = (float) ($validated['freight_amount'] ?? 0);
            $discountType = (string) ($validated['discount_type'] ?? 'amount');
            $discountValue = (float) ($validated['discount_value'] ?? 0);
            $discountTotal = $discountType === 'percent' ? $subtotal * $discountValue / 100 : $discountValue;
            $discountTotal = min($discountTotal, $subtotal);
            $taxPercent = (bool) ($validated['tax_enabled'] ?? false) ? (float) ($validated['tax_percent'] ?? 11) : 0;
            $taxBase = max(0, $subtotal - $discountTotal + $freightAmount);
            $taxTotal = $taxBase * $taxPercent / 100;
            $grandTotal = $taxBase + $taxTotal;
            $saleIds = collect($draft['dispatches'])->pluck('sale_id')->filter()->unique()->values();

            $payload = [
                'number' => $this->generateNumber(),
                'customer_id' => (int) $validated['customer_id'],
                'sale_id' => $saleIds->count() === 1 ? $saleIds->first() : null,
                'shipment_id' => null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'amount_paid' => 0,
                'balance_due' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('customer_invoices', 'freight_amount')) {
                $payload['freight_amount'] = $freightAmount;
            }

            $invoiceId = DB::table('customer_invoices')->insertGetId($payload);

            foreach ($draft['dispatches'] as $dispatch) {
                DB::table('customer_invoice_dispatches')->insert([
                    'customer_invoice_id' => $invoiceId,
                    'internal_usage_id' => $dispatch['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($lines as $line) {
                DB::table('customer_invoice_lines')->insert([
                    'customer_invoice_id' => $invoiceId,
                    'shipment_line_id' => null,
                    'dispatch_id' => $line['dispatch_id'],
                    'internal_usage_line_id' => $line['internal_usage_line_id'],
                    'sale_line_id' => $line['sale_line_id'],
                    'item_id' => $line['item_id'],
                    'uom_id' => $line['uom_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'tax_percent' => $taxPercent,
                    'tax_amount' => $subtotal > 0 ? $taxTotal * ((float) $line['line_total'] / $subtotal) : 0,
                    'line_total' => $line['line_total'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return (object) ['id' => $invoiceId];
        });

        return redirect()->route('apps.customer-invoices.show', $invoice->id)->with('success', 'Draft invoice berhasil dibuat dari dispatch terpilih.');
    }

    public function show(string $id)
    {
        $invoice = DB::table('customer_invoices as ci')
            ->leftJoin('customers as c', 'c.id', '=', 'ci.customer_id')
            ->where('ci.id', $id)
            ->select([
                'ci.*',
                DB::raw('COALESCE(c.customer_name, "-") as customer_name'),
                DB::raw('COALESCE(c.customer_code, "-") as customer_code'),
            ])
            ->first();

        abort_unless($invoice, 404);

        $lines = DB::table('customer_invoice_lines as cil')
            ->leftJoin('items as i', 'i.id', '=', 'cil.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'cil.uom_id')
            ->leftJoin('internal_usages as iu', 'iu.id', '=', 'cil.dispatch_id')
            ->where('cil.customer_invoice_id', $id)
            ->orderBy('cil.id')
            ->get([
                'cil.*',
                DB::raw('COALESCE(i.sku, "-") as item_sku'),
                DB::raw('COALESCE(i.name, "-") as item_name'),
                DB::raw('COALESCE(u.code, "-") as uom_code'),
                DB::raw('COALESCE(iu.number, "-") as dispatch_number'),
            ]);

        $dispatches = DB::table('customer_invoice_dispatches as cid')
            ->join('internal_usages as iu', 'iu.id', '=', 'cid.internal_usage_id')
            ->where('cid.customer_invoice_id', $id)
            ->orderBy('iu.document_date')
            ->get(['iu.id', 'iu.number', 'iu.document_date', 'iu.source_number', 'iu.status']);

        return Inertia::render('Apps/Sales/CustomerInvoices/Show', [
            'invoice' => $invoice,
            'lines' => $lines,
            'dispatches' => $dispatches,
        ]);
    }

    public function edit(string $id)
    {
        return Inertia::render('Apps/Sales/CustomerInvoices/Form', ['id' => $id]);
    }

    public function update(Request $request, string $id)
    {
        return back()->with('success', 'Updated');
    }

    public function destroy(string $id)
    {
        return back()->with('success', 'Deleted');
    }

    public function post(string $id)
    {
        DB::transaction(function () use ($id): void {
            $invoice = DB::table('customer_invoices')->lockForUpdate()->where('id', $id)->first();
            abort_unless($invoice, 404);
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Hanya draft invoice yang bisa diposting.']);
            }

            $lines = DB::table('customer_invoice_lines')->where('customer_invoice_id', $id)->get();
            foreach ($lines->groupBy('sale_line_id') as $saleLineId => $groupedLines) {
                if (! $saleLineId) {
                    continue;
                }

                DB::table('sales_lines')
                    ->where('id', $saleLineId)
                    ->increment('qty_invoiced', (float) $groupedLines->sum('qty'), ['updated_at' => now()]);
            }

            DB::table('customer_invoices')->where('id', $id)->update([
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'balance_due' => $invoice->grand_total - $invoice->amount_paid,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Invoice posted.');
    }

    public function cancel(string $id)
    {
        $invoice = DB::table('customer_invoices')->where('id', $id)->first();
        abort_unless($invoice, 404);
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya draft invoice yang bisa dibatalkan tanpa reversal.']);
        }

        DB::table('customer_invoices')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);

        return back()->with('success', 'Cancelled');
    }

    private function parseDispatchIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return collect($raw)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        }

        return collect(explode(',', (string) $raw))->map(fn ($id) => (int) trim($id))->filter()->unique()->values()->all();
    }

    private function buildDraftFromDispatches(array $dispatchIds, ?int $customerId = null, bool $lock = false): array
    {
        $dispatchIds = collect($dispatchIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($dispatchIds->isEmpty()) {
            throw ValidationException::withMessages(['dispatch_ids' => 'Pilih minimal satu dispatch.']);
        }

        $dispatchQuery = DB::table('internal_usages as iu')
            ->leftJoin('warehouses as w', 'w.id', '=', 'iu.warehouse_id')
            ->whereIn('iu.id', $dispatchIds)
            ->where('iu.status', 'POSTED')
            ->where(function ($query): void {
                $query->where('iu.source_type', 'sales_order')->orWhereNotNull('iu.sale_id');
            })
            ->select([
                'iu.id',
                'iu.number',
                'iu.document_date',
                'iu.customer_id',
                'iu.sale_id',
                'iu.source_id',
                'iu.source_number',
                DB::raw('COALESCE(w.name, "-") as warehouse_label'),
            ]);

        if ($lock) {
            $dispatchQuery->lockForUpdate();
        }

        if ($customerId) {
            $dispatchQuery->where('iu.customer_id', $customerId);
        }

        $dispatches = $dispatchQuery->get();
        if ($dispatches->count() !== $dispatchIds->count()) {
            throw ValidationException::withMessages(['dispatch_ids' => 'Semua dispatch harus POSTED, berasal dari Sales Order, dan customer-nya sama.']);
        }

        $customerIds = $dispatches->pluck('customer_id')->filter()->unique();
        if ($customerIds->count() !== 1) {
            throw ValidationException::withMessages(['dispatch_ids' => 'Dispatch gabungan invoice harus untuk customer yang sama.']);
        }

        $alreadyInvoiced = DB::table('customer_invoice_dispatches as cid')
            ->join('customer_invoices as ci', 'ci.id', '=', 'cid.customer_invoice_id')
            ->whereIn('cid.internal_usage_id', $dispatchIds)
            ->where('ci.status', '!=', 'cancelled')
            ->pluck('cid.internal_usage_id');

        if ($alreadyInvoiced->isNotEmpty()) {
            throw ValidationException::withMessages(['dispatch_ids' => 'Dispatch '.implode(', ', $alreadyInvoiced->all()).' sudah masuk invoice lain.']);
        }

        $customer = DB::table('customers')->where('id', $customerIds->first())->first();
        $lines = $this->buildDraftLines($dispatches);
        $subtotal = (float) $lines->sum('line_total');
        $paymentTermDays = (int) ($customer->payment_term_days ?? 0);

        return [
            'customer' => $customer,
            'dispatches' => $dispatches->map(fn ($dispatch) => (array) $dispatch)->values()->all(),
            'lines' => $lines->values()->all(),
            'defaults' => [
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays($paymentTermDays)->toDateString(),
                'discount_type' => 'amount',
                'discount_value' => 0,
                'tax_enabled' => true,
                'tax_percent' => 11,
                'freight_amount' => 0,
                'subtotal' => $subtotal,
            ],
        ];
    }

    private function buildDraftLines(Collection $dispatches): Collection
    {
        $dispatchIds = $dispatches->pluck('id');

        return DB::table('internal_usage_lines as iul')
            ->join('internal_usages as iu', 'iu.id', '=', 'iul.internal_usage_id')
            ->join('sales_lines as sl', 'sl.id', '=', 'iul.sale_line_id')
            ->leftJoin('items as i', 'i.id', '=', 'iul.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'iul.uom_id')
            ->whereIn('iul.internal_usage_id', $dispatchIds)
            ->orderBy('iu.document_date')
            ->orderBy('iul.id')
            ->get([
                'iul.id as internal_usage_line_id',
                'iul.internal_usage_id as dispatch_id',
                'iu.number as dispatch_number',
                'iul.sale_line_id',
                'iul.item_id',
                'iul.uom_id',
                'iul.qty_used as qty',
                'sl.unit_price',
                DB::raw('COALESCE(i.sku, "-") as item_sku'),
                DB::raw('COALESCE(i.name, "-") as item_name'),
                DB::raw('COALESCE(u.code, "-") as uom_code'),
            ])
            ->map(function (object $line): array {
                $qty = (float) $line->qty;
                $unitPrice = (float) $line->unit_price;

                return [
                    'internal_usage_line_id' => (int) $line->internal_usage_line_id,
                    'dispatch_id' => (int) $line->dispatch_id,
                    'dispatch_number' => $line->dispatch_number,
                    'sale_line_id' => (int) $line->sale_line_id,
                    'item_id' => (int) $line->item_id,
                    'uom_id' => $line->uom_id ? (int) $line->uom_id : null,
                    'item_sku' => $line->item_sku,
                    'item_name' => $line->item_name,
                    'uom_code' => $line->uom_code,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $qty * $unitPrice,
                ];
            });
    }

    private function generateNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = 'INV-'.now()->format('Ym').'-';
            $last = DB::table('customer_invoices')
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');
            $sequence = $last ? ((int) substr((string) $last, -5)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }
}
