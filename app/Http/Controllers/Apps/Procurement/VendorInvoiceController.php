<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorInvoiceRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorInvoiceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VendorInvoiceController extends Controller
{
    public function index(Vendor $vendor)
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);

        $invoices = VendorInvoice::query()
            ->where('vendor_id', $vendor->id)
            ->where('company_id', $companyId)
            ->latest('invoice_date')
            ->paginate(10)
            ->through(fn ($inv) => [
                'id' => $inv->id,
                'invoice_no_internal' => $inv->invoice_no_internal,
                'vendor_invoice_no' => $inv->vendor_invoice_no,
                'invoice_date' => $inv->invoice_date,
                'due_date' => $inv->due_date,
                'subtotal' => $inv->subtotal,
                'discount_amount' => $inv->discount_amount,
                'tax_amount' => $inv->tax_amount,
                'wht_tax_amount' => $inv->wht_tax_amount ?? 0,
                'grand_total' => $inv->grand_total,
                'net_payable_amount' => $inv->net_payable_amount ?? $inv->grand_total,
                'paid_amount' => $inv->paid_amount,
                'outstanding_amount' => $inv->outstanding_amount,
                'status' => strtolower((string) $inv->status),
            ]);

        return response()->json(['invoices' => $invoices]);
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
        $documentsPayload = (array) $request->input('documents', []);

        DB::transaction(function () use ($data, $vendor, $companyId, $vendorInvoiceNo, $linesBySource, $available, &$uploadedDocumentCount, $documentsPayload, $request) {
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

            foreach ($linePayloads as $linePayload) {
                VendorInvoiceLine::create(array_merge($linePayload, ['vendor_invoice_id' => $invoice->id]));
            }

            $uploadedDocumentCount = $this->attachDocumentsToInvoice($invoice, $documentsPayload, $request);
        });

        $message = 'Vendor invoice berhasil dibuat.';
        if ($uploadedDocumentCount > 0) {
            $message .= " {$uploadedDocumentCount} dokumen berhasil diupload.";
        }

        return redirect()->route('apps.procurement.vendors.show', ['vendor' => $vendor->id, 'tab' => 'invoices'])
            ->with('success', $message);
    }


    public function show(VendorInvoice $vendorInvoice)
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        return Inertia::render('Apps/Procurement/VendorInvoices/Show', [
            'invoice' => $invoice,
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

        DB::transaction(function () use ($invoice, $data, $linesBySource, $available) {
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

            $this->attachDocumentsToInvoice($invoice, $data['documents'] ?? []);
        });

        return redirect()->route('apps.procurement.vendors.show', ['vendor' => $invoice->vendor_id, 'tab' => 'invoices'])
            ->with('success', 'Vendor invoice berhasil diperbarui.');
    }

    public function approve(VendorInvoice $vendorInvoice): RedirectResponse
    {
        $invoice = $this->authorizedInvoice($vendorInvoice);

        if (strtolower((string) $invoice->status) !== 'draft') {
            return back()->with('error', 'Hanya invoice draft yang bisa di-approve.');
        }

        $invoice->status = 'APPROVED';
        $invoice->save();

        return back()->with('success', 'Vendor invoice berhasil di-approve.');
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
            ->groupBy('rel.id', 'rel.item_id', 'rel.qty', 'rel.price', 're.number', 'po.po_no', 'i.name', 'i.sku')
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
