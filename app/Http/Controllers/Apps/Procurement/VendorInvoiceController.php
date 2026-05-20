<?php

namespace App\Http\Controllers\Apps\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreVendorInvoiceRequest;
use App\Models\Procurement\Vendor;
use App\Models\Procurement\VendorInvoice;
use App\Models\Procurement\VendorInvoiceLine;
use Illuminate\Support\Facades\DB;
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
        ]);
    }

    public function store(StoreVendorInvoiceRequest $request, Vendor $vendor)
    {
        $companyId = (int) (auth()->user()?->company_id ?? 1);
        $data = $request->validated();
        $linesByReceipt = collect($data['lines'])->keyBy('receipt_line_id');

        $available = collect($this->availableReceivingLines($vendor->id, $companyId))->keyBy('receipt_line_id');
        foreach ($linesByReceipt as $receiptLineId => $line) {
            $source = $available->get((int) $receiptLineId);
            if (! $source) throw ValidationException::withMessages(['lines' => 'Receipt line tidak valid untuk vendor/company ini.']);
            if ((float) $line['qty_invoiced'] > (float) $source['qty_available_to_invoice']) {
                throw ValidationException::withMessages(['lines' => 'Qty invoiced melebihi qty available to invoice.']);
            }
        }

        $vendorInvoiceNo = trim((string) ($data['vendor_invoice_no'] ?? ''));
        if ($vendorInvoiceNo !== '' && VendorInvoice::where('vendor_id', $vendor->id)->where('vendor_invoice_no', $vendorInvoiceNo)->exists()) {
            throw ValidationException::withMessages(['vendor_invoice_no' => 'Nomor invoice vendor sudah digunakan untuk vendor ini.']);
        }

        DB::transaction(function () use ($data, $vendor, $companyId, $vendorInvoiceNo, $linesByReceipt, $available) {
            $subtotal = 0;
            $linePayloads = [];
            foreach ($linesByReceipt as $receiptLineId => $line) {
                $src = $available[(int) $receiptLineId];
                $lineTotal = (float) $line['qty_invoiced'] * (float) $line['unit_price'];
                $subtotal += $lineTotal;
                $linePayloads[] = [
                    'receipt_line_id' => $src['receipt_line_id'],
                    'po_line_id' => $src['po_line_id'],
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
        });

        return redirect()->route('apps.procurement.vendors.show', ['vendor' => $vendor->id, 'tab' => 'invoices'])
            ->with('success', 'Vendor invoice berhasil dibuat.');
    }

    private function availableReceivingLines(int $vendorId, int $companyId): array
    {
        return DB::table('goods_receipt_lines as grl')
            ->join('goods_receipts as gr', 'gr.id', '=', 'grl.goods_receipt_id')
            ->leftJoin('purchase_order_lines as pol', 'pol.id', '=', 'grl.po_line_id')
            ->leftJoin('items as i', 'i.id', '=', 'grl.item_id')
            ->leftJoin('vendor_invoice_lines as vil', 'vil.receipt_line_id', '=', 'grl.id')
            ->leftJoin('vendor_invoices as vi', 'vi.id', '=', 'vil.vendor_invoice_id')
            ->where('gr.vendor_id', $vendorId)
            ->where('gr.company_id', $companyId)
            ->whereIn(DB::raw('LOWER(gr.status)'), ['posted', 'completed'])
            ->groupBy('grl.id', 'grl.po_line_id', 'grl.item_id', 'grl.qty_received', 'grl.qty_accepted', 'grl.unit_price', 'gr.receipt_no', 'pol.po_no', 'pol.unit_price', 'i.name', 'i.sku')
            ->selectRaw('grl.id as receipt_line_id, grl.po_line_id, grl.item_id, COALESCE(grl.qty_accepted, grl.qty_received, 0) as qty_received')
            ->selectRaw('COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0) as qty_already_invoiced')
            ->selectRaw('(COALESCE(grl.qty_accepted, grl.qty_received, 0) - COALESCE(SUM(CASE WHEN vi.deleted_at IS NULL THEN vil.qty_invoiced ELSE 0 END),0)) as qty_available_to_invoice')
            ->selectRaw('COALESCE(pol.po_no, "-") as po_no, gr.receipt_no as receiving_no, COALESCE(i.name, i.sku, "-") as item_name, i.sku')
            ->selectRaw('COALESCE(pol.unit_price, grl.unit_price, 0) as unit_price_default')
            ->havingRaw('qty_available_to_invoice > 0')
            ->get()
            ->map(fn ($r) => [
                'receipt_line_id' => (int) $r->receipt_line_id,
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
            ])->values()->all();
    }

    private function nextInternalNo(): string
    {
        return 'VINV-'.now()->format('YmdHis');
    }
}
