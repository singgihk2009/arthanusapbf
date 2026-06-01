<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
            ->leftJoin('sales as s', 's.id', '=', 'ci.sale_id')
            ->leftJoin('users as su', 'su.id', '=', 's.salesman_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'c.salesman_id')
            ->leftJoin('users as pu', 'pu.id', '=', 'ci.posted_by')
            ->where('ci.id', $id)
            ->select([
                'ci.*',
                DB::raw('COALESCE(c.customer_name, "-") as customer_name'),
                DB::raw('COALESCE(c.customer_code, "-") as customer_code'),
                DB::raw('COALESCE(c.address, "-") as customer_address'),
                DB::raw('COALESCE(c.city, "") as customer_city'),
                DB::raw('COALESCE(c.province, "") as customer_province'),
                DB::raw('COALESCE(c.phone, "") as customer_phone'),
                DB::raw('COALESCE(c.npwp, "-") as customer_npwp'),
                DB::raw('c.salesman_id as customer_salesman_id'),
                DB::raw('COALESCE(cu.name, "") as customer_salesman_name'),
                DB::raw('s.number as sales_order_number'),
                DB::raw('s.document_date as sales_order_date'),
                DB::raw('s.salesman_id as salesman_id'),
                DB::raw('COALESCE(su.name, "") as salesman_name'),
                DB::raw('COALESCE(pu.name, "") as posted_by_name'),
            ])
            ->first();

        abort_unless($invoice, 404);

        $lines = DB::table('customer_invoice_lines as cil')
            ->leftJoin('items as i', 'i.id', '=', 'cil.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'cil.uom_id')
            ->leftJoin('internal_usages as iu', 'iu.id', '=', 'cil.dispatch_id')
            ->leftJoin('internal_usage_lines as iul', 'iul.id', '=', 'cil.internal_usage_line_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'iul.batch_id')
            ->where('cil.customer_invoice_id', $id)
            ->orderBy('cil.id')
            ->get([
                'cil.*',
                DB::raw('COALESCE(i.sku, "-") as item_sku'),
                DB::raw('COALESCE(i.name, "-") as item_name'),
                DB::raw('COALESCE(u.code, "-") as uom_code'),
                DB::raw('COALESCE(iu.number, "-") as dispatch_number'),
                DB::raw('COALESCE(ib.batch_no, "-") as batch_no'),
                DB::raw('ib.expired_date as expired_date'),
            ]);

        $dispatches = DB::table('customer_invoice_dispatches as cid')
            ->join('internal_usages as iu', 'iu.id', '=', 'cid.internal_usage_id')
            ->where('cid.customer_invoice_id', $id)
            ->orderBy('iu.document_date')
            ->get(['iu.id', 'iu.number', 'iu.document_date', 'iu.source_number', 'iu.status']);

        $company = Schema::hasTable('company_profiles')
            ? DB::table('company_profiles')->orderBy('id')->first()
            : null;

        return Inertia::render('Apps/Sales/CustomerInvoices/Show', [
            'invoice' => $invoice,
            'lines' => $lines,
            'dispatches' => $dispatches,
            'company' => $company,
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
        $invoiceId = DB::transaction(function () use ($id): int {
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

            $this->upsertSalesInvoiceFinanceHubOutbox((int) $id);

            return (int) $id;
        });

        $this->sendSalesInvoiceFinanceHubEvent($invoiceId);

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


    private function upsertSalesInvoiceFinanceHubOutbox(int $invoiceId): void
    {
        if (! Schema::hasTable('integration_outbox')) {
            return;
        }

        $payload = $this->buildSalesInvoiceFinanceHubPayload($invoiceId);
        $encoded = json_encode($payload);
        $hash = hash('sha256', (string) $encoded);

        DB::table('integration_outbox')->updateOrInsert(
            ['idempotency_key' => $payload['idempotency_key']],
            [
                'event_type' => $payload['event_name'],
                'aggregate_type' => 'sales_invoice',
                'aggregate_id' => $invoiceId,
                'payload_json' => $encoded,
                'payload_hash' => $hash,
                'status' => 'ready',
                'available_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function buildSalesInvoiceFinanceHubPayload(int $invoiceId): array
    {
        $invoice = DB::table('customer_invoices as ci')
            ->leftJoin('customers as c', 'c.id', '=', 'ci.customer_id')
            ->leftJoin('sales as s', 's.id', '=', 'ci.sale_id')
            ->where('ci.id', $invoiceId)
            ->select([
                'ci.*',
                DB::raw('COALESCE(c.customer_code, "") as customer_code'),
                DB::raw('COALESCE(c.customer_name, "") as customer_name'),
                DB::raw('s.number as sales_order_no'),
            ])
            ->first();

        abort_unless($invoice, 404);

        $dispatches = DB::table('customer_invoice_dispatches as cid')
            ->join('internal_usages as iu', 'iu.id', '=', 'cid.internal_usage_id')
            ->where('cid.customer_invoice_id', $invoiceId)
            ->orderBy('iu.document_date')
            ->orderBy('iu.id')
            ->get(['iu.id', 'iu.number', 'iu.source_number']);

        $snapshotCosts = DB::table('inv_transactions as it')
            ->join('inv_transaction_items as iti', 'iti.inv_transaction_id', '=', 'it.id')
            ->where('it.source_table', 'internal_usages')
            ->whereIn('it.source_id', $dispatches->pluck('id'))
            ->select([
                'it.source_id as dispatch_id',
                'iti.product_id as item_id',
                'iti.batch_id',
                DB::raw('AVG(iti.unit_cost_snapshot) as unit_cost'),
            ])
            ->groupBy('it.source_id', 'iti.product_id', 'iti.batch_id')
            ->get()
            ->keyBy(fn (object $row): string => $row->dispatch_id.'|'.$row->item_id.'|'.($row->batch_id ?: ''));

        $lines = DB::table('customer_invoice_lines as cil')
            ->leftJoin('internal_usages as iu', 'iu.id', '=', 'cil.dispatch_id')
            ->leftJoin('internal_usage_lines as iul', 'iul.id', '=', 'cil.internal_usage_line_id')
            ->leftJoin('items as i', 'i.id', '=', 'cil.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'cil.uom_id')
            ->leftJoin('item_batches as ib', 'ib.id', '=', 'iul.batch_id')
            ->leftJoinSub(
                DB::table('inv_balances')
                    ->where('company_id', 1)
                    ->select('product_id', DB::raw('AVG(avg_cost) as avg_cost'))
                    ->groupBy('product_id'),
                'bal',
                'bal.product_id',
                '=',
                'cil.item_id'
            )
            ->where('cil.customer_invoice_id', $invoiceId)
            ->orderBy('cil.id')
            ->get([
                'cil.*',
                'iul.batch_id',
                DB::raw('COALESCE(iu.number, "") as dispatch_no'),
                DB::raw('COALESCE(i.sku, "") as item_code'),
                DB::raw('COALESCE(i.name, "") as item_name'),
                DB::raw('COALESCE(ib.batch_no, "") as batch_no'),
                DB::raw('COALESCE(u.code, "") as uom_code'),
                DB::raw('COALESCE(bal.avg_cost, 0) as fallback_unit_cost'),
            ])
            ->map(function (object $line) use ($snapshotCosts): array {
                $qty = (float) $line->qty;
                $key = $line->dispatch_id.'|'.$line->item_id.'|'.($line->batch_id ?: '');
                $unitCost = (float) ($snapshotCosts->get($key)->unit_cost ?? $line->fallback_unit_cost ?? 0);
                $salesAmount = (float) $line->line_total;
                $costAmount = $qty * $unitCost;

                return [
                    'dispatch_no' => (string) $line->dispatch_no,
                    'item_code' => (string) $line->item_code,
                    'item_name' => (string) $line->item_name,
                    'batch_no' => (string) $line->batch_no,
                    'qty' => $qty,
                    'uom' => (string) $line->uom_code,
                    'selling_price' => (float) $line->unit_price,
                    'sales_amount' => $salesAmount,
                    'cost_amount' => $costAmount,
                ];
            });

        $documentNo = (string) $invoice->number;
        $postedAt = $invoice->posted_at ? \Carbon\Carbon::parse($invoice->posted_at) : now();
        $postingDate = (string) ($invoice->invoice_date ?: $postedAt->toDateString());
        $subtotal = (float) ($invoice->subtotal ?? 0);
        $discountTotal = (float) ($invoice->discount_total ?? 0);
        $shippingFee = (float) ($invoice->freight_amount ?? 0);
        $taxBase = max(0, $subtotal - $discountTotal + $shippingFee);
        $taxRate = $taxBase > 0 ? (float) ($invoice->tax_total ?? 0) / $taxBase : 0;
        $cogs = (float) $lines->sum('cost_amount');
        $firstDispatch = $dispatches->first();
        $salesOrderNo = (string) ($invoice->sales_order_no ?: ($firstDispatch->source_number ?? ''));

        return [
            'source_module' => 'sales',
            'event_name' => 'sales.invoice.posted',
            'event_datetime' => $postedAt->copy()->timezone(config('app.timezone', 'UTC'))->toIso8601String(),
            'idempotency_key' => 'sales-invoice:'.$documentNo.':posted:v1',
            'source_document_type' => 'sales_invoice',
            'source_document_id' => $documentNo,
            'source_document_no' => $documentNo,
            'schema_version' => 'v1',
            'payload' => [
                'posting_mode' => 'rule',
                'transaction_type' => 'sales.invoice.standard',
                'posting_date' => $postingDate,
                'reference_no' => $documentNo,
                'description' => 'Sales invoice '.$documentNo.' - '.($invoice->customer_name ?: '-'),
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
                'customer' => [
                    'customer_code' => (string) $invoice->customer_code,
                    'customer_name' => (string) $invoice->customer_name,
                ],
                'tax' => [
                    'code' => $taxRate > 0 ? 'PPN'.rtrim(rtrim(number_format($taxRate * 100, 2, '.', ''), '0'), '.') : null,
                    'rate' => $taxRate,
                    'calculation_base' => 'after_discount',
                ],
                'amounts' => [
                    'subtotal' => $subtotal,
                    'discount' => $discountTotal,
                    'shipping_fee' => $shippingFee,
                    'cogs' => $cogs,
                ],
                'sales_invoice' => [
                    'invoice_no' => $documentNo,
                    'invoice_date' => $postingDate,
                    'due_date' => (string) ($invoice->due_date ?: $postingDate),
                    'dispatch_no' => (string) ($firstDispatch->number ?? ''),
                    'sales_order_no' => $salesOrderNo,
                ],
                'dispatch_cost' => [
                    'cost_source' => 'dispatch',
                    'dispatch_no' => (string) ($firstDispatch->number ?? ''),
                    'total_cogs' => $cogs,
                ],
                'lines_detail' => $lines->values()->all(),
            ],
        ];
    }

    private function sendSalesInvoiceFinanceHubEvent(int $invoiceId): void
    {
        if (! Schema::hasTable('integration_outbox')) {
            return;
        }

        $outbox = DB::table('integration_outbox')
            ->where('aggregate_type', 'sales_invoice')
            ->where('aggregate_id', $invoiceId)
            ->first();

        if (! $outbox || in_array($outbox->status, ['sent', 'acked'], true)) {
            return;
        }

        $eventsUrl = $this->salesInvoiceFinanceHubEventsUrl();
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            $this->markSalesInvoiceFinanceHubOutboxFailed((int) $outbox->id, 'Konfigurasi Finance Hub Sales Invoice belum lengkap. Pastikan FINANCE_HUB_BASE_URL, FINANCE_HUB_CLIENT_KEY, dan FINANCE_HUB_CLIENT_SECRET tersedia.');
            return;
        }

        $eventPayload = json_decode((string) $outbox->payload_json, true) ?: [];
        $payload = array_merge([
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
        ], $eventPayload);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout((int) config('services.finance_hub.timeout', 10))
                ->post($eventsUrl, $payload);

            if ($response->successful()) {
                DB::table('integration_outbox')->where('id', $outbox->id)->update([
                    'status' => 'sent',
                    'attempts' => DB::raw('attempts + 1'),
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                return;
            }

            $message = sprintf('Finance Hub HTTP %s: %s', $response->status(), mb_strimwidth($response->body(), 0, 500));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
        }

        $this->markSalesInvoiceFinanceHubOutboxFailed((int) $outbox->id, $message);
    }

    private function salesInvoiceFinanceHubEventsUrl(): ?string
    {
        $configuredUrl = config('services.finance_hub.sales_invoice_events_url');
        if (is_string($configuredUrl) && trim($configuredUrl) !== '') {
            return trim($configuredUrl);
        }

        $baseUrl = config('services.finance_hub.base_url');
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim(trim($baseUrl), '/').'/api/integrations/events';
        }

        return null;
    }

    private function markSalesInvoiceFinanceHubOutboxFailed(int $outboxId, string $message): void
    {
        DB::table('integration_outbox')->where('id', $outboxId)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
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
            ->leftJoin('shipments as sh', 'sh.dispatch_id', '=', 'iu.id')
            ->leftJoin('sales as so', function ($join): void {
                $join->on('so.id', '=', DB::raw("COALESCE(iu.sale_id, sh.sale_id, CASE WHEN iu.source_type = 'sales_order' THEN iu.source_id END)"));
            })
            ->whereIn('iu.id', $dispatchIds)
            ->where('iu.status', 'POSTED')
            ->where(function ($query): void {
                $query->where('iu.source_type', 'sales_order')
                    ->orWhereNotNull('iu.sale_id')
                    ->orWhereNotNull('sh.sale_id');
            })
            ->select([
                'iu.id',
                'iu.number',
                'iu.document_date',
                DB::raw('COALESCE(iu.customer_id, sh.customer_id, so.customer_id) as customer_id'),
                DB::raw('COALESCE(iu.sale_id, sh.sale_id, so.id) as sale_id'),
                DB::raw('COALESCE(iu.source_id, sh.sale_id, so.id) as source_id'),
                DB::raw('COALESCE(iu.source_number, so.number) as source_number'),
                DB::raw('COALESCE(w.name, "-") as warehouse_label'),
            ]);

        if ($lock) {
            $dispatchQuery->lockForUpdate();
        }

        if ($customerId) {
            $dispatchQuery->where(function ($query) use ($customerId): void {
                $query->where('iu.customer_id', $customerId)
                    ->orWhere('sh.customer_id', $customerId)
                    ->orWhere('so.customer_id', $customerId);
            });
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
            ->leftJoin('shipments as sh', 'sh.dispatch_id', '=', 'iu.id')
            ->leftJoin('shipment_lines as shl', function ($join): void {
                $join->on('shl.shipment_id', '=', 'sh.id')
                    ->on('shl.item_id', '=', 'iul.item_id')
                    ->whereRaw('(shl.batch_id = iul.batch_id OR (shl.batch_id IS NULL AND iul.batch_id IS NULL))')
                    ->whereRaw('ABS(shl.qty_shipped - iul.qty_used) < 0.0001');
            })
            ->join('sales_lines as sl', function ($join): void {
                $join->on('sl.id', '=', 'iul.sale_line_id')
                    ->orOn('sl.id', '=', 'shl.sale_line_id');
            })
            ->leftJoin('items as i', 'i.id', '=', 'iul.item_id')
            ->leftJoin('uoms as u', 'u.id', '=', 'iul.uom_id')
            ->whereIn('iul.internal_usage_id', $dispatchIds)
            ->orderBy('iu.document_date')
            ->orderBy('iul.id')
            ->get([
                'iul.id as internal_usage_line_id',
                'iul.internal_usage_id as dispatch_id',
                'iu.number as dispatch_number',
                DB::raw('COALESCE(iul.sale_line_id, shl.sale_line_id) as sale_line_id'),
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
