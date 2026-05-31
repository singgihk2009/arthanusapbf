<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorInvoiceRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorInvoiceLine;
use App\Models\Procurement\VendorPaymentLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VendorInvoiceController extends Controller
{
    public function index(Request $request, ?Vendor $vendor = null)
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);


        $paidTotalsSubquery = VendorPaymentLine::query()
            ->select('vendor_invoice_id', DB::raw('SUM(payment_amount + COALESCE(wht_amount, 0)) as paid_total'))
            ->whereHas('payment', function ($query) {
                $query->whereIn('status', ['APPROVED', 'PAID', 'POSTED']);
            })
            ->groupBy('vendor_invoice_id');

        $query = VendorInvoice::query()
            ->with('vendor:id,name,vendor_name')
            ->leftJoinSub($paidTotalsSubquery, 'invoice_paid_totals', function ($join) {
                $join->on('invoice_paid_totals.vendor_invoice_id', '=', 'vendor_invoices.id');
            })
            ->select('vendor_invoices.*', DB::raw('COALESCE(invoice_paid_totals.paid_total, 0) as paid_amount_calc'))
            ->where('vendor_invoices.company_id', $companyId)
            ->latest('vendor_invoices.invoice_date');

        if ($vendor) {
            $query->where('vendor_id', $vendor->id);
        } else {
            $search = trim((string) $request->input('search', ''));
            $paymentStatus = strtolower(trim((string) $request->input('status_invoice', '')));
            $documentStatus = strtoupper(trim((string) $request->input('status_dokumen', '')));

            if ($paymentStatus !== '') {
                if (in_array($paymentStatus, ['lunas', 'paid'], true)) {
                    $query->whereRaw('(COALESCE(vendor_invoices.net_payable_amount, vendor_invoices.grand_total, 0) - COALESCE(invoice_paid_totals.paid_total, 0)) <= 0');
                } elseif (in_array($paymentStatus, ['belum_lunas'], true)) {
                    $query->whereRaw('(COALESCE(vendor_invoices.net_payable_amount, vendor_invoices.grand_total, 0) - COALESCE(invoice_paid_totals.paid_total, 0)) > 0');
                } elseif (in_array($paymentStatus, ['partial'], true)) {
                    $query->whereRaw('COALESCE(invoice_paid_totals.paid_total, 0) > 0 AND (COALESCE(vendor_invoices.net_payable_amount, vendor_invoices.grand_total, 0) - COALESCE(invoice_paid_totals.paid_total, 0)) > 0');
                } elseif (in_array($paymentStatus, ['unpaid'], true)) {
                    $query->whereRaw('COALESCE(invoice_paid_totals.paid_total, 0) <= 0 AND (COALESCE(vendor_invoices.net_payable_amount, vendor_invoices.grand_total, 0) - COALESCE(invoice_paid_totals.paid_total, 0)) > 0');
                }
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $like = '%'.$search.'%';
                    $q->where('invoice_no_internal', 'like', $like)
                        ->orWhere('vendor_invoice_no', 'like', $like)
                        ->orWhereHas('vendor', function ($vendorQuery) use ($like) {
                            $vendorQuery->where('vendor_name', 'like', $like)
                                ->orWhere('name', 'like', $like);
                        });
                });
            }

            if ($documentStatus !== '') {
                $query->whereRaw('UPPER(COALESCE(status, "")) = ?', [$documentStatus]);
            }
        }

        $invoices = $query->paginate(10)->withQueryString();

        $invoiceIds = collect($invoices->items())->pluck('id')->filter()->values();
        $paidByInvoice = collect();

        if ($invoiceIds->isNotEmpty()) {
            $paidByInvoice = VendorPaymentLine::query()
                ->select('vendor_invoice_id', DB::raw('SUM(payment_amount + COALESCE(wht_amount, 0)) as paid_total'))
                ->whereIn('vendor_invoice_id', $invoiceIds)
                ->whereHas('payment', function ($query) {
                    $query->whereIn('status', ['APPROVED', 'PAID', 'POSTED']);
                })
                ->groupBy('vendor_invoice_id')
                ->pluck('paid_total', 'vendor_invoice_id');
        }

        $invoices->setCollection($invoices->getCollection()->map(function (VendorInvoice $inv) use ($paidByInvoice) {
            $netPayable = (float) ($inv->net_payable_amount ?? $inv->grand_total ?? 0);
            $calculatedPaid = (float) ($inv->paid_amount_calc ?? ($paidByInvoice[$inv->id] ?? 0));
            $outstanding = max(0, $netPayable - $calculatedPaid);

            return [
                'id' => $inv->id,
                'invoice_no_internal' => $inv->invoice_no_internal,
                'vendor_invoice_no' => $inv->vendor_invoice_no,
                'vendor_id' => $inv->vendor_id ? (int) $inv->vendor_id : null,
                'vendor_name' => $inv->vendor?->vendor_name ?: $inv->vendor?->name,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'subtotal' => $inv->subtotal,
                'discount_amount' => $inv->discount_amount,
                'tax_amount' => $inv->tax_amount,
                'wht_tax_amount' => $inv->wht_tax_amount ?? 0,
                'grand_total' => $inv->grand_total,
                'net_payable_amount' => $netPayable,
                'paid_amount' => $calculatedPaid,
                'outstanding_amount' => $outstanding,
                'status' => strtolower((string) $inv->status),
                'payment_status' => $outstanding <= 0 ? 'paid' : ($calculatedPaid > 0 ? 'partial' : 'unpaid'),
            ];
        }));


        if ($vendor) {
            return response()->json(['invoices' => $invoices]);
        }

        return Inertia::render('Apps/Procurement/VendorInvoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'status_invoice' => strtolower((string) $request->input('status_invoice', '')),
                'status_dokumen' => strtoupper((string) $request->input('status_dokumen', '')),
            ],
            'statusInvoiceOptions' => [
                ['value' => 'lunas', 'label' => 'Lunas'],
                ['value' => 'belum_lunas', 'label' => 'Belum Lunas'],
            ],
            'statusDokumenOptions' => ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED', 'PAID', 'CANCELLED'],
        ]);
    }

    public function create(Vendor $vendor)
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $lines = $this->availableReceivingLines($vendor->id, $companyId);

        return Inertia::render('Apps/Procurement/VendorInvoices/Form', [
            'vendor' => $vendor,
            'internalInvoiceNoPreview' => $this->nextInternalNo(),
            'receivingLines' => $lines,
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function store(StoreVendorInvoiceRequest $request, Vendor $vendor)
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $data = $request->validated();
        $linesBySource = collect($data['lines'])->keyBy(fn ($line) => $line['source_line_type'].':'.$line['source_line_id']);

        $available = collect($this->availableReceivingLines($vendor->id, $companyId))->keyBy('source_key');
        foreach ($linesBySource as $sourceKey => $line) {
            $source = $available->get($sourceKey);
            if (! $source) throw ValidationException::withMessages(['lines' => 'Receipt line tidak valid untuk vendor/company ini.']);
            if ((float) $line['qty_invoiced'] > (float) $source['qty_available_to_invoice']) {
                throw ValidationException::withMessages(['lines' => 'Qty invoiced melebihi qty available to invoice.']);
            }
        }

        $vendorInvoiceNo = trim((string) ($data['vendor_invoice_no'] ?? ''));
        if ($vendorInvoiceNo !== '' && VendorInvoice::where('vendor_id', $vendor->id)->where('vendor_invoice_no', $vendorInvoiceNo)->exists()) {
            throw ValidationException::withMessages(['vendor_invoice_no' => 'Nomor invoice vendor sudah digunakan untuk vendor ini.']);
        }

        $uploadedDocumentCount = 0;
        $createdInvoiceId = null;
        $documentsPayload = (array) $request->input('documents', []);

        DB::transaction(function () use ($data, $vendor, $companyId, $vendorInvoiceNo, $linesBySource, $available, &$uploadedDocumentCount, $documentsPayload, $request, &$createdInvoiceId) {
            $subtotal = 0;
            $linePayloads = [];
            foreach ($linesBySource as $sourceKey => $line) {
                $src = $available[$sourceKey];
                $poLineId = $src['po_line_id'] ?? null;
                if ($poLineId !== null && ! DB::table('purchase_order_lines')->where('id', $poLineId)->exists()) {
                    $poLineId = null;
                }
                $lineTotal = (float) $line['qty_invoiced'] * (float) $line['unit_price'];
                $subtotal += $lineTotal;
                $linePayloads[] = [
                    'receipt_line_id' => $src['source_line_type'] === 'goods_receipt_line' ? $src['source_line_id'] : null,
                    'receiving_entry_line_id' => $src['source_line_type'] === 'receiving_entry_line' ? $src['source_line_id'] : null,
                    'po_line_id' => $poLineId,
                    'item_id' => $src['item_id'],
                    'description' => $src['item_name'],
                    'qty_invoiced' => $line['qty_invoiced'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => 0,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $freight = (float) ($data['freight_amount'] ?? 0);
            $taxRate = (float) ($data['tax_rate'] ?? 11);
            $taxBase = max(0, $subtotal - $discount);
            $taxAmount = $taxBase * $taxRate / 100;
            $grandTotal = $taxBase + $taxAmount + $freight;
            $whtRate = (float) ($data['wht_tax_rate'] ?? 0);
            $whtBase = (float) ($data['wht_tax_base_amount'] ?? $taxBase);
            $whtAmount = $whtBase * $whtRate / 100;
            $netPayable = $grandTotal - $whtAmount;
            $paid = 0;
            $outstanding = $netPayable - $paid;

            $invoice = VendorInvoice::create([
                'company_id' => $companyId,
                'vendor_id' => $vendor->id,
                'invoice_no_internal' => $this->nextInternalNo(),
                'vendor_invoice_no' => $vendorInvoiceNo !== '' ? $vendorInvoiceNo : $this->nextInternalNo(),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? $data['invoice_date'],
                'currency_code' => $data['currency_code'] ?? 'IDR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'tax_base_amount' => $taxBase,
                'tax_amount' => $taxAmount,
                'freight_amount' => $freight,
                'grand_total' => $grandTotal,
                'wht_tax_type' => $data['wht_tax_type'] ?? null,
                'wht_tax_rate' => $whtRate,
                'wht_tax_base_amount' => $whtBase,
                'wht_tax_amount' => $whtAmount,
                'net_payable_amount' => $netPayable,
                'paid_amount' => $paid,
                'outstanding_amount' => $outstanding,
                'notes' => $data['notes'] ?? null,
                'status' => 'DRAFT',
            ]);
            $createdInvoiceId = $invoice->id;

            foreach ($linePayloads as $linePayload) {
                VendorInvoiceLine::create(array_merge($linePayload, ['vendor_invoice_id' => $invoice->id]));
            }

            $uploadedDocumentCount = $this->attachDocumentsToInvoice($invoice, $documentsPayload, $request);
        });

        $message = 'Vendor invoice berhasil dibuat.';
        if ($uploadedDocumentCount > 0) {
            $message .= " {$uploadedDocumentCount} dokumen berhasil diupload.";
        }

        return redirect()->route('apps.procurement.vendor-invoices.edit', ['vendor_invoice' => $createdInvoiceId])
            ->with('success', $message.' Silakan cek daftar dokumen terupload di bawah form.');
    }


    public function show(VendorInvoice $vendorInvoice)
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        $invoice->loadMissing(['vendor:id,name,vendor_code', 'lines.item:id,name,sku']);

        return Inertia::render('Apps/Procurement/VendorInvoices/Show', [
            'invoice' => $invoice,
            'uploadedDocuments' => Document::query()->where('owner_type', 'vendor_invoice')->where('owner_id', $invoice->id)->with('documentType:id,name,code')->latest()->get(),
        ]);
    }

    public function edit(VendorInvoice $vendorInvoice)
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $availableLines = collect($this->availableReceivingLines((int) $invoice->vendor_id, $companyId))->keyBy('source_key');

        $selectedLines = $invoice->lines()->get()->map(function ($line) use ($availableLines) {
            $sourceLineType = $line->receipt_line_id ? 'goods_receipt_line' : 'receiving_entry_line';
            $sourceLineId = (int) ($line->receipt_line_id ?: $line->receiving_entry_line_id);
            $sourceKey = $sourceLineType.':'.$sourceLineId;
            $available = $availableLines->get($sourceKey);

            if (! $available) {
                $available = [
                    'source_key' => $sourceKey,
                    'source_line_type' => $sourceLineType,
                    'source_line_id' => $sourceLineId,
                    'po_line_id' => $line->po_line_id,
                    'item_id' => $line->item_id,
                    'qty_received' => (float) $line->qty_invoiced,
                    'qty_already_invoiced' => 0,
                    'qty_available_to_invoice' => (float) $line->qty_invoiced,
                    'po_no' => '-',
                    'receiving_no' => '-',
                    'item_name' => $line->description,
                    'sku' => null,
                    'unit_price' => (float) $line->unit_price,
                    'line_total' => (float) $line->line_total,
                ];
                $availableLines->put($sourceKey, $available);
            }

            return [
                'source_line_type' => $sourceLineType,
                'source_line_id' => $sourceLineId,
                'qty_invoiced' => (float) $line->qty_invoiced,
                'unit_price' => (float) $line->unit_price,
            ];
        })->values();

        return Inertia::render('Apps/Procurement/VendorInvoices/Edit', [
            'vendor' => $invoice->vendor,
            'invoice' => $invoice,
            'receivingLines' => $availableLines->values()->all(),
            'selectedLines' => $selectedLines,
            'documentTypes' => DocumentType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'uploadedDocuments' => Document::query()->where('owner_type', 'vendor_invoice')->where('owner_id', $invoice->id)->with('documentType:id,name,code')->latest()->get(),
        ]);
    }

    public function update(StoreVendorInvoiceRequest $request, VendorInvoice $vendorInvoice): RedirectResponse
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        if (strtolower((string) $invoice->status) !== 'draft') {
            return back()->with('error', 'Hanya invoice draft yang bisa diedit.');
        }

        $data = $request->validated();
        $documentsPayload = (array) $request->input('documents', []);
        $linesBySource = collect($data['lines'])->keyBy(fn ($line) => $line['source_line_type'].':'.$line['source_line_id']);
        $available = collect($this->availableReceivingLines((int) $invoice->vendor_id, (int) $invoice->company_id))->keyBy('source_key');

        foreach ($invoice->lines as $existingLine) {
            $sourceType = $existingLine->receipt_line_id ? 'goods_receipt_line' : 'receiving_entry_line';
            $sourceId = (int) ($existingLine->receipt_line_id ?: $existingLine->receiving_entry_line_id);
            $key = $sourceType.':'.$sourceId;
            if (! $available->has($key)) {
                $available->put($key, [
                    'source_key' => $key,
                    'source_line_type' => $sourceType,
                    'source_line_id' => $sourceId,
                    'po_line_id' => $existingLine->po_line_id,
                    'item_id' => $existingLine->item_id,
                    'qty_available_to_invoice' => (float) $existingLine->qty_invoiced,
                    'item_name' => $existingLine->description,
                ]);
            } else {
                $merged = $available->get($key);
                $merged['qty_available_to_invoice'] = (float) ($merged['qty_available_to_invoice'] ?? 0) + (float) $existingLine->qty_invoiced;
                $available->put($key, $merged);
            }
        }

        foreach ($linesBySource as $sourceKey => $line) {
            $source = $available->get($sourceKey);
            if (! $source) throw ValidationException::withMessages(['lines' => 'Receipt line tidak valid untuk vendor/company ini.']);
            if ((float) $line['qty_invoiced'] > (float) ($source['qty_available_to_invoice'] ?? 0)) {
                throw ValidationException::withMessages(['lines' => 'Qty invoiced melebihi qty available to invoice.']);
            }
        }

        $uploadedDocumentCount = 0;
        DB::transaction(function () use ($invoice, $data, $linesBySource, $available, $documentsPayload, $request, &$uploadedDocumentCount) {
            $subtotal = 0;
            $linePayloads = [];
            foreach ($linesBySource as $sourceKey => $line) {
                $src = $available[$sourceKey];
                $lineTotal = (float) $line['qty_invoiced'] * (float) $line['unit_price'];
                $subtotal += $lineTotal;
                $linePayloads[] = [
                    'receipt_line_id' => $src['source_line_type'] === 'goods_receipt_line' ? $src['source_line_id'] : null,
                    'receiving_entry_line_id' => $src['source_line_type'] === 'receiving_entry_line' ? $src['source_line_id'] : null,
                    'po_line_id' => $src['po_line_id'] ?? null,
                    'item_id' => $src['item_id'] ?? null,
                    'description' => $src['item_name'] ?? '-',
                    'qty_invoiced' => $line['qty_invoiced'],
                    'unit_price' => $line['unit_price'],
                    'tax_amount' => 0,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = (float) ($data['discount_amount'] ?? 0);
            $freight = (float) ($data['freight_amount'] ?? 0);
            $taxRate = (float) ($data['tax_rate'] ?? 11);
            $taxBase = max(0, $subtotal - $discount);
            $taxAmount = $taxBase * $taxRate / 100;
            $grandTotal = $taxBase + $taxAmount + $freight;
            $whtRate = (float) ($data['wht_tax_rate'] ?? 0);
            $whtBase = (float) ($data['wht_tax_base_amount'] ?? $taxBase);
            $whtAmount = $whtBase * $whtRate / 100;
            $netPayable = $grandTotal - $whtAmount;

            $invoice->update([
                'vendor_invoice_no' => trim((string) ($data['vendor_invoice_no'] ?? '')) ?: $invoice->vendor_invoice_no,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? $data['invoice_date'],
                'currency_code' => $data['currency_code'] ?? 'IDR',
                'exchange_rate' => $data['exchange_rate'] ?? 1,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'tax_base_amount' => $taxBase,
                'tax_amount' => $taxAmount,
                'freight_amount' => $freight,
                'grand_total' => $grandTotal,
                'wht_tax_type' => $data['wht_tax_type'] ?? null,
                'wht_tax_rate' => $whtRate,
                'wht_tax_base_amount' => $whtBase,
                'wht_tax_amount' => $whtAmount,
                'net_payable_amount' => $netPayable,
                'outstanding_amount' => $netPayable - (float) $invoice->paid_amount,
                'notes' => $data['notes'] ?? null,
            ]);

            $invoice->lines()->delete();
            foreach ($linePayloads as $payload) {
                VendorInvoiceLine::create(array_merge($payload, ['vendor_invoice_id' => $invoice->id]));
            }

            $uploadedDocumentCount = $this->attachDocumentsToInvoice($invoice, $documentsPayload, $request);
        });

        $message = 'Vendor invoice berhasil diperbarui.';
        if ($uploadedDocumentCount > 0) {
            $message .= " {$uploadedDocumentCount} dokumen berhasil diupload.";
        }

        return redirect()->route('apps.procurement.vendor-invoices.edit', ['vendor_invoice' => $invoice->id])
            ->with('success', $message);
    }

    public function approve(VendorInvoice $vendorInvoice): RedirectResponse
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        if (strtolower((string) $invoice->status) !== 'draft') {
            return back()->with('error', 'Hanya invoice draft yang bisa di-approve.');
        }

        $invoice = DB::transaction(function () use ($invoice): VendorInvoice {
            $invoice->status = 'POSTED';
            $invoice->posted_at = now();
            $invoice->posted_by = auth()->id();
            $invoice->save();

            $invoice = $invoice->fresh(['vendor']);
            $this->upsertVendorInvoiceFinanceHubOutbox($invoice);

            return $invoice;
        });

        $this->sendVendorInvoiceFinanceHubEvent($invoice->id);

        return back()->with('success', 'Vendor invoice berhasil di-posting.');
    }

    public function destroy(VendorInvoice $vendorInvoice): RedirectResponse
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        if (strtolower((string) $invoice->status) !== 'draft') {
            return back()->with('error', 'Hanya invoice draft yang bisa dihapus.');
        }

        $invoice->delete();

        return back()->with('success', 'Vendor invoice berhasil dihapus.');
    }


    private function upsertVendorInvoiceFinanceHubOutbox(VendorInvoice $invoice): void
    {
        $payload = $this->buildVendorInvoiceFinanceHubPayload($invoice);
        $encoded = json_encode($payload);
        $hash = hash('sha256', (string) $encoded);

        DB::table('integration_outbox')->updateOrInsert(
            ['idempotency_key' => $payload['idempotency_key']],
            [
                'event_type' => $payload['event_name'],
                'aggregate_type' => 'vendor_invoice',
                'aggregate_id' => $invoice->id,
                'payload_json' => $encoded,
                'payload_hash' => $hash,
                'status' => 'ready',
                'available_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function buildVendorInvoiceFinanceHubPayload(VendorInvoice $invoice): array
    {
        $documentNo = (string) ($invoice->invoice_no_internal ?: $invoice->vendor_invoice_no ?: ('VI-'.$invoice->id));
        $postingDate = $invoice->invoice_date ? $invoice->invoice_date->toDateString() : now()->toDateString();
        $postedAt = $invoice->posted_at ? $invoice->posted_at : now();
        $taxAmount = (float) ($invoice->tax_amount ?? 0);
        $freightAmount = (float) ($invoice->freight_amount ?? 0);
        $withholdingTaxAmount = (float) ($invoice->wht_tax_amount ?? 0);
        $discountAmount = (float) ($invoice->discount_amount ?? 0);
        $grandTotal = (float) ($invoice->grand_total ?? 0);
        $payableTotal = (float) ($invoice->net_payable_amount ?? ($grandTotal - $withholdingTaxAmount));

        return [
            'event_name' => 'vendor.invoice.posted',
            'event_datetime' => $postedAt->copy()->utc()->toJSON(),
            'idempotency_key' => 'VI-POSTED-'.$documentNo,
            'source_document_type' => 'vendor_invoice',
            'source_document_id' => (string) $invoice->id,
            'source_document_no' => $documentNo,
            'schema_version' => 'v1',
            'payload' => [
                'transaction_type' => 'vendor.invoice.standard',
                'currency_code' => (string) ($invoice->currency_code ?: 'IDR'),
                'posting_date' => $postingDate,
                'entry_date' => $postedAt->toDateString(),
                'reference_no' => $documentNo,
                'description' => 'Vendor Invoice '.$documentNo,
                'amounts' => [
                    'invoice' => (float) ($invoice->subtotal ?? 0),
                    'tax' => $taxAmount,
                    'freight' => $freightAmount,
                    'withholding_tax' => $withholdingTaxAmount,
                    'purchase_discount' => $discountAmount,
                    'payable_total' => $payableTotal,
                ],
            ],
        ];
    }

    private function sendVendorInvoiceFinanceHubEvent(int $invoiceId): void
    {
        $eventsUrl = config('services.finance_hub.vendor_invoice_events_url');
        $clientKey = config('services.finance_hub.client_key');
        $clientSecret = config('services.finance_hub.client_secret');

        if (! $eventsUrl || ! $clientKey || ! $clientSecret) {
            return;
        }

        $outbox = DB::table('integration_outbox')
            ->where('aggregate_type', 'vendor_invoice')
            ->where('aggregate_id', $invoiceId)
            ->first();

        if (! $outbox) {
            return;
        }

        $payload = json_decode((string) $outbox->payload_json, true) ?: [];
        $payload['client_key'] = $clientKey;
        $payload['client_secret'] = $clientSecret;

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

        DB::table('integration_outbox')->where('id', $outbox->id)->update([
            'status' => 'failed',
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => $message,
            'updated_at' => now(),
        ]);
    }

    private function availableReceivingLines(int $vendorId, int $companyId): array
    {
        $goodsReceiptLines = DB::table('goods_receipt_lines as grl')
            ->join('goods_receipts as gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->leftJoin('purchase_order_lines as pol', 'pol.id', '=', 'grl.po_line_id')
            ->leftJoin('purchase_orders as po', 'po.id', '=', 'pol.purchase_order_id')
            ->leftJoin('purchase_orders as po_header', 'po_header.id', '=', 'gr.po_id')
            ->leftJoin('items as i', 'i.id', '=', 'grl.item_id')
            ->leftJoin('vendor_invoice_lines as vil', 'vil.receipt_line_id', '=', 'grl.id')
            ->leftJoin('vendor_invoices as vi', 'vi.id', '=', 'vil.vendor_invoice_id')
            ->whereRaw('COALESCE(gr.vendor_id, po.vendor_id, po_header.vendor_id) = ?', [$vendorId])
            ->whereRaw('COALESCE(gr.company_id, 1) = ?', [$companyId])
            ->whereIn(DB::raw('LOWER(gr.status)'), ['posted', 'completed'])
            ->groupBy('grl.id', 'grl.po_line_id', 'grl.item_id', 'grl.qty_received', 'grl.qty_accepted', 'grl.unit_price', 'gr.receipt_no', 'gr.gr_number', 'gr.number', 'po.po_no', 'po_header.po_no', 'pol.unit_price', 'i.name', 'i.sku')
            ->selectRaw('grl.id as receipt_line_id, grl.po_line_id, grl.item_id, COALESCE(grl.qty_accepted, grl.qty_received, 0) as qty_received')
            ->selectRaw('COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0) as qty_already_invoiced')
            ->selectRaw('(COALESCE(grl.qty_accepted, grl.qty_received, 0) - COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0)) as qty_available_to_invoice')
            ->selectRaw('COALESCE(po.po_no, po_header.po_no, "-") as po_no, COALESCE(gr.receipt_no, gr.gr_number, gr.number, "-") as receiving_no, COALESCE(i.name, i.sku, "-") as item_name, i.sku')
            ->selectRaw('COALESCE(pol.unit_price, grl.unit_price, 0) as unit_price_default')
            ->havingRaw('qty_available_to_invoice > 0')
            ->get()
            ->map(fn ($r) => [
                'source_key' => 'goods_receipt_line:'.(int) $r->receipt_line_id,
                'source_line_type' => 'goods_receipt_line',
                'source_line_id' => (int) $r->receipt_line_id,
                'po_line_id' => $r->po_line_id,
                'item_id' => $r->item_id,
                'qty_received' => (float) $r->qty_received,
                'qty_already_invoiced' => (float) $r->qty_already_invoiced,
                'qty_available_to_invoice' => (float) $r->qty_available_to_invoice,
                'po_no' => $r->po_no,
                'receiving_no' => $r->receiving_no,
                'item_name' => $r->item_name,
                'sku' => $r->sku,
                'unit_price' => (float) $r->unit_price_default,
                'line_total' => (float) $r->qty_available_to_invoice * (float) $r->unit_price_default,
            ]);

        $receivingEntryLinesQuery = DB::table('receiving_entry_lines as rel')
            ->join('receiving_entries as re', 're.id', '=', 'rel.receiving_entry_id')
            ->leftJoin('purchase_orders as po', function ($join) {
                $join->on('po.id', '=', 're.source_id')->where('re.source_type', '=', 'purchase_order');
            })
            ->leftJoin('items as i', 'i.id', '=', 'rel.item_id')
            ->leftJoin('vendor_invoice_lines as vil', 'vil.receiving_entry_line_id', '=', 'rel.id')
            ->leftJoin('vendor_invoices as vi', 'vi.id', '=', 'vil.vendor_invoice_id')
            ->whereRaw('COALESCE(re.vendor_id, po.vendor_id) = ?', [$vendorId])
            ->whereIn(DB::raw('LOWER(re.status)'), ['posted', 'completed']);

        if (Schema::hasColumn('receiving_entries', 'business_id')) {
            $receivingEntryLinesQuery->whereRaw('COALESCE(re.business_id, 1) = ?', [$companyId]);
        } elseif (Schema::hasColumn('receiving_entries', 'company_id')) {
            $receivingEntryLinesQuery->whereRaw('COALESCE(re.company_id, 1) = ?', [$companyId]);
        }

        $receivingEntryLines = $receivingEntryLinesQuery
            ->groupBy('rel.id', 'rel.item_id', 'rel.qty', 'rel.price', 're.number', 're.reference', 'po.po_no', 'i.name', 'i.sku')
            ->selectRaw('rel.id as receiving_entry_line_id, rel.item_id, null as po_line_id, rel.qty as qty_received')
            ->selectRaw('COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0) as qty_already_invoiced')
            ->selectRaw('(rel.qty - COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0)) as qty_available_to_invoice')
            ->selectRaw('COALESCE(po.po_no, "-") as po_no, COALESCE(re.number, re.reference, "-") as receiving_no, COALESCE(i.name, i.sku, "-") as item_name, i.sku')
            ->selectRaw('COALESCE(rel.price, 0) as unit_price_default')
            ->havingRaw('qty_available_to_invoice > 0')
            ->get()
            ->map(fn ($r) => [
                'source_key' => 'receiving_entry_line:'.(int) $r->receiving_entry_line_id,
                'source_line_type' => 'receiving_entry_line',
                'source_line_id' => (int) $r->receiving_entry_line_id,
                'po_line_id' => $r->po_line_id,
                'item_id' => $r->item_id,
                'qty_received' => (float) $r->qty_received,
                'qty_already_invoiced' => (float) $r->qty_already_invoiced,
                'qty_available_to_invoice' => (float) $r->qty_available_to_invoice,
                'po_no' => $r->po_no,
                'receiving_no' => $r->receiving_no,
                'item_name' => $r->item_name,
                'sku' => $r->sku,
                'unit_price' => (float) $r->unit_price_default,
                'line_total' => (float) $r->qty_available_to_invoice * (float) $r->unit_price_default,
            ]);

        return $goodsReceiptLines->concat($receivingEntryLines)->values()->all();
    }


    private function authorizedInvoice(VendorInvoice $vendorInvoice): VendorInvoice
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);

        abort_unless((int) $vendorInvoice->company_id === $companyId, 404);

        return $vendorInvoice;
    }

    private function nextInternalNo(): string
    {
        return 'VINV-'.now()->format('YmdHis');
    }

    private function attachDocumentsToInvoice(VendorInvoice $invoice, array $documents, StoreVendorInvoiceRequest $request): int
    {
        $uploadedCount = 0;

        foreach ($documents as $index => $document) {
            $file = $request->file("documents.$index.file");
            $documentTypeId = $document['document_type_id'] ?? null;
            if (! $file || ! $documentTypeId) {
                continue;
            }

            $path = $file->store('documents/vendor-invoices', 'public');
            Document::create([
                'business_id' => $invoice->company_id,
                'owner_type' => 'vendor_invoice',
                'owner_id' => $invoice->id,
                'document_type_id' => $documentTypeId,
                'title' => $document['title'] ?: ('Invoice Document #'.$invoice->invoice_no_internal),
                'document_number' => $document['document_number'] ?? null,
                'issue_date' => $document['issue_date'] ?? null,
                'expiry_date' => $document['expiry_date'] ?? null,
                'notes' => $document['notes'] ?? null,
                'file_path' => $path,
                'storage_disk' => 'public',
                'original_file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
                'status' => 'pending_review',
            ]);
            $uploadedCount++;
        }

        return $uploadedCount;
    }
}
