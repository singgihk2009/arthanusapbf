<?php

namespace App\Http\Controllers\Apps\Setup;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StockService;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ManualPurchaseIntegrationController extends Controller
{
    private const SHEETS = [
        'po_headers',
        'po_lines',
        'receiving_headers',
        'receiving_lines',
        'invoice_headers',
        'invoice_lines',
        'payment_headers',
        'payment_lines',
    ];

    public function __construct(
        private readonly StockService $stockService,
        private readonly UomConversionService $uomConversionService,
    ) {
    }

    public function index(): Response
    {
        $batches = DB::table('manual_purchase_integration_batches')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (object $batch): object {
                $batch->summary = json_decode((string) ($batch->summary_json ?? '{}'), true) ?: [];
                $batch->errors = array_slice(json_decode((string) ($batch->errors_json ?? '[]'), true) ?: [], 0, 10);
                $batch->warnings = array_slice(json_decode((string) ($batch->warnings_json ?? '[]'), true) ?: [], 0, 10);

                return $batch;
            });

        return Inertia::render('Setup/ManualPurchaseIntegration/Index', [
            'batches' => $batches,
            'purposes' => ['INITIAL_HISTORY', 'BRANCH_INTEGRATION', 'MANUAL_BACKFILL', 'CORRECTION'],
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/manual-purchase-integration-template-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($path, $this->templateSheets());

        return response()->download($path, 'manual-purchase-integration-template.xlsx')->deleteFileAfterSend(true);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_system' => ['required', 'string', 'max:80'],
            'source_branch_code' => ['required', 'string', 'max:80'],
            'import_purpose' => ['required', 'string', 'max:80'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $sheets = $this->parseWorkbook($file->getRealPath());
        [$errors, $warnings, $summary] = $this->validateWorkbook($sheets, $validated['source_system'], $validated['source_branch_code']);

        $batchId = DB::transaction(function () use ($validated, $file, $sheets, $errors, $warnings, $summary, $request): int {
            $batchId = DB::table('manual_purchase_integration_batches')->insertGetId([
                'batch_no' => 'MPI-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'source_system' => $validated['source_system'],
                'source_branch_code' => $validated['source_branch_code'],
                'import_purpose' => $validated['import_purpose'],
                'file_name' => $file->getClientOriginalName(),
                'file_hash' => hash_file('sha256', $file->getRealPath()) ?: null,
                'status' => count($errors) > 0 ? 'validation_failed' : 'validated',
                'summary_json' => json_encode($summary),
                'errors_json' => json_encode($errors),
                'warnings_json' => json_encode($warnings),
                'preview_json' => json_encode($sheets),
                'uploaded_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($sheets as $sheetName => $rows) {
                foreach ($rows as $index => $row) {
                    DB::table('manual_purchase_integration_rows')->insert([
                        'batch_id' => $batchId,
                        'sheet_name' => $sheetName,
                        'row_number' => $index + 2,
                        'status' => $this->rowHasError($errors, $sheetName, $index + 2) ? 'error' : 'valid',
                        'row_data_json' => json_encode($row),
                        'messages_json' => json_encode($this->messagesForRow($errors, $warnings, $sheetName, $index + 2)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $batchId;
        });

        $message = count($errors) > 0
            ? 'Import template tersimpan sebagai batch validasi gagal. Perbaiki error sebelum commit.'
            : 'Import template berhasil divalidasi. Silakan review lalu commit.';

        return to_route('apps.setup.manual-purchase-integration.index')
            ->with(count($errors) > 0 ? 'error' : 'success', $message)
            ->with('batch_id', $batchId);
    }

    public function commit(Request $request, int $batch): RedirectResponse
    {
        $header = DB::table('manual_purchase_integration_batches')->where('id', $batch)->lockForUpdate()->first();
        abort_unless($header, 404);
        abort_if($header->status === 'committed', 422, 'Batch sudah committed.');
        abort_if($header->status !== 'validated', 422, 'Batch belum valid, tidak bisa commit.');

        $sheets = json_decode((string) $header->preview_json, true) ?: [];
        [$errors] = $this->validateWorkbook($sheets, (string) $header->source_system, (string) $header->source_branch_code);
        abort_if(count($errors) > 0, 422, 'Batch memiliki error validasi terbaru, upload ulang file yang sudah diperbaiki.');

        DB::transaction(function () use ($header, $sheets, $request): void {
            $context = [
                'po_ids' => [],
                'po_line_ids' => [],
                'receiving_ids' => [],
                'receiving_line_ids' => [],
                'invoice_ids' => [],
                'payment_ids' => [],
            ];

            $this->commitPurchaseOrders($sheets, $header, $request->user()?->id, $context);
            $this->commitReceivings($sheets, $header, $request->user()?->id, $context);
            $this->commitInvoices($sheets, $header, $request->user()?->id, $context);
            $this->commitPayments($sheets, $header, $request->user()?->id, $context);
            $this->refreshPurchaseOrderFulfillment($context['po_ids']);

            DB::table('manual_purchase_integration_batches')->where('id', $header->id)->update([
                'status' => 'committed',
                'committed_by' => $request->user()?->id,
                'committed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('apps.setup.manual-purchase-integration.index')
            ->with('success', 'Batch manual purchase integration berhasil committed.');
    }

    public function show(int $batch): JsonResponse
    {
        $header = DB::table('manual_purchase_integration_batches')->where('id', $batch)->first();
        abort_unless($header, 404);

        return response()->json([
            'batch' => $header,
            'rows' => DB::table('manual_purchase_integration_rows')->where('batch_id', $batch)->orderBy('sheet_name')->orderBy('row_number')->limit(500)->get(),
            'links' => DB::table('manual_purchase_integration_document_links')->where('batch_id', $batch)->orderBy('id')->get(),
        ]);
    }

    /** @param array<string, array<int, array<string, string>>> $sheets */
    private function validateWorkbook(array $sheets, string $sourceSystem, string $sourceBranchCode): array
    {
        $errors = [];
        $warnings = [];
        $summary = [];

        foreach (self::SHEETS as $sheet) {
            $rows = collect($sheets[$sheet] ?? [])->filter(fn (array $row): bool => ! $this->isRowEmpty($row))->values();
            $summary[$sheet] = ['rows' => $rows->count()];
        }

        $this->requireHeaders($sheets, 'po_headers', ['po_no', 'po_date', 'vendor_code', 'warehouse_code', 'status'], $errors);
        $this->requireHeaders($sheets, 'po_lines', ['po_no', 'line_no', 'item_sku', 'uom_code', 'qty_ordered', 'unit_price'], $errors);
        $this->requireHeaders($sheets, 'receiving_headers', ['receiving_no', 'receiving_date', 'po_no', 'vendor_code', 'warehouse_code', 'status'], $errors);
        $this->requireHeaders($sheets, 'receiving_lines', ['receiving_no', 'line_no', 'po_no', 'po_line_no', 'item_sku', 'uom_code', 'qty_received', 'unit_price'], $errors);
        $this->requireHeaders($sheets, 'invoice_headers', ['invoice_no_internal', 'vendor_invoice_no', 'invoice_date', 'due_date', 'vendor_code', 'status', 'grand_total'], $errors);
        $this->requireHeaders($sheets, 'invoice_lines', ['invoice_no_internal', 'line_no', 'item_sku', 'qty_invoiced', 'unit_price', 'line_total'], $errors);
        $this->requireHeaders($sheets, 'payment_headers', ['payment_no', 'payment_date', 'vendor_code', 'payment_method', 'status'], $errors);
        $this->requireHeaders($sheets, 'payment_lines', ['payment_no', 'line_no', 'invoice_no_internal', 'payment_amount'], $errors);

        $poNos = [];
        foreach ($this->sheetRows($sheets, 'po_headers') as $index => $row) {
            $rowNo = $index + 2;
            $poNo = $this->string($row, 'po_no');
            $vendorId = $this->vendorId($this->string($row, 'vendor_code'));
            $warehouseId = $this->warehouseId($this->string($row, 'warehouse_code'));
            if ($poNo === '') $this->addMessage($errors, 'po_headers', $rowNo, 'po_no wajib diisi.');
            if (isset($poNos[$poNo])) $this->addMessage($errors, 'po_headers', $rowNo, "po_no {$poNo} duplikat di file.");
            $poNos[$poNo] = true;
            if (! $vendorId) $this->addMessage($errors, 'po_headers', $rowNo, 'vendor_code tidak ditemukan di master vendor.');
            if (! $warehouseId) $this->addMessage($errors, 'po_headers', $rowNo, 'warehouse_code tidak ditemukan di master warehouse.');
            if ($poNo !== '' && $this->purchaseOrderExists($poNo)) $this->addMessage($errors, 'po_headers', $rowNo, "PO {$poNo} sudah ada di transaksi regular/integrasi lain.");
            if ($poNo !== '' && $this->documentLinkExists($sourceSystem, $sourceBranchCode, 'purchase_order', $poNo)) $this->addMessage($errors, 'po_headers', $rowNo, "PO {$poNo} sudah pernah di-commit dari source yang sama.");
        }

        $poLineKeys = [];
        foreach ($this->sheetRows($sheets, 'po_lines') as $index => $row) {
            $rowNo = $index + 2;
            $poNo = $this->string($row, 'po_no');
            $lineNo = $this->string($row, 'line_no');
            $itemId = $this->itemId($this->string($row, 'item_sku'));
            $uomId = $this->uomId($this->string($row, 'uom_code'));
            if (! isset($poNos[$poNo])) $this->addMessage($errors, 'po_lines', $rowNo, 'po_no tidak ditemukan di po_headers.');
            if ($lineNo === '') $this->addMessage($errors, 'po_lines', $rowNo, 'line_no wajib diisi.');
            $key = $poNo.'#'.$lineNo;
            if (isset($poLineKeys[$key])) $this->addMessage($errors, 'po_lines', $rowNo, "Line PO {$key} duplikat.");
            $poLineKeys[$key] = true;
            if (! $itemId) $this->addMessage($errors, 'po_lines', $rowNo, 'item_sku tidak ditemukan.');
            if (! $uomId) $this->addMessage($errors, 'po_lines', $rowNo, 'uom_code tidak ditemukan.');
            if ($this->decimal($row, 'qty_ordered') <= 0) $this->addMessage($errors, 'po_lines', $rowNo, 'qty_ordered harus > 0.');
        }

        $receivingNos = [];
        foreach ($this->sheetRows($sheets, 'receiving_headers') as $index => $row) {
            $rowNo = $index + 2;
            $receivingNo = $this->string($row, 'receiving_no');
            $poNo = $this->string($row, 'po_no');
            if ($receivingNo === '') $this->addMessage($errors, 'receiving_headers', $rowNo, 'receiving_no wajib diisi.');
            if (isset($receivingNos[$receivingNo])) $this->addMessage($errors, 'receiving_headers', $rowNo, "receiving_no {$receivingNo} duplikat.");
            $receivingNos[$receivingNo] = true;
            if (! isset($poNos[$poNo])) $this->addMessage($errors, 'receiving_headers', $rowNo, 'po_no tidak ditemukan di po_headers.');
            if (! $this->vendorId($this->string($row, 'vendor_code'))) $this->addMessage($errors, 'receiving_headers', $rowNo, 'vendor_code tidak ditemukan.');
            if (! $this->warehouseId($this->string($row, 'warehouse_code'))) $this->addMessage($errors, 'receiving_headers', $rowNo, 'warehouse_code tidak ditemukan.');
            if ($receivingNo !== '' && DB::table('receiving_entries')->where('number', $receivingNo)->exists()) $this->addMessage($errors, 'receiving_headers', $rowNo, "receiving_no {$receivingNo} sudah ada.");
        }

        $receivingLineKeys = [];
        foreach ($this->sheetRows($sheets, 'receiving_lines') as $index => $row) {
            $rowNo = $index + 2;
            $receivingNo = $this->string($row, 'receiving_no');
            $lineNo = $this->string($row, 'line_no');
            $poLineKey = $this->string($row, 'po_no').'#'.$this->string($row, 'po_line_no');
            if (! isset($receivingNos[$receivingNo])) $this->addMessage($errors, 'receiving_lines', $rowNo, 'receiving_no tidak ditemukan di receiving_headers.');
            if (! isset($poLineKeys[$poLineKey])) $this->addMessage($errors, 'receiving_lines', $rowNo, 'po_no + po_line_no tidak ditemukan di po_lines.');
            if (! $this->itemId($this->string($row, 'item_sku'))) $this->addMessage($errors, 'receiving_lines', $rowNo, 'item_sku tidak ditemukan.');
            if (! $this->uomId($this->string($row, 'uom_code'))) $this->addMessage($errors, 'receiving_lines', $rowNo, 'uom_code tidak ditemukan.');
            if ($this->decimal($row, 'qty_received') <= 0) $this->addMessage($errors, 'receiving_lines', $rowNo, 'qty_received harus > 0.');
            $key = $receivingNo.'#'.$lineNo;
            if (isset($receivingLineKeys[$key])) $this->addMessage($errors, 'receiving_lines', $rowNo, "Line receiving {$key} duplikat.");
            $receivingLineKeys[$key] = true;
        }

        $invoiceNos = [];
        foreach ($this->sheetRows($sheets, 'invoice_headers') as $index => $row) {
            $rowNo = $index + 2;
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            if ($invoiceNo === '') $this->addMessage($errors, 'invoice_headers', $rowNo, 'invoice_no_internal wajib diisi.');
            if (isset($invoiceNos[$invoiceNo])) $this->addMessage($errors, 'invoice_headers', $rowNo, "invoice {$invoiceNo} duplikat.");
            $invoiceNos[$invoiceNo] = true;
            if (! $this->vendorId($this->string($row, 'vendor_code'))) $this->addMessage($errors, 'invoice_headers', $rowNo, 'vendor_code tidak ditemukan.');
            if ($invoiceNo !== '' && DB::table('vendor_invoices')->where('invoice_no_internal', $invoiceNo)->exists()) $this->addMessage($errors, 'invoice_headers', $rowNo, "invoice_no_internal {$invoiceNo} sudah ada.");
        }

        foreach ($this->sheetRows($sheets, 'invoice_lines') as $index => $row) {
            $rowNo = $index + 2;
            if (! isset($invoiceNos[$this->string($row, 'invoice_no_internal')])) $this->addMessage($errors, 'invoice_lines', $rowNo, 'invoice_no_internal tidak ditemukan di invoice_headers.');
            if ($this->string($row, 'receiving_no') !== '' && $this->string($row, 'receiving_line_no') !== '' && ! isset($receivingLineKeys[$this->string($row, 'receiving_no').'#'.$this->string($row, 'receiving_line_no')])) {
                $this->addMessage($errors, 'invoice_lines', $rowNo, 'receiving_no + receiving_line_no tidak ditemukan.');
            }
            if (! $this->itemId($this->string($row, 'item_sku'))) $this->addMessage($errors, 'invoice_lines', $rowNo, 'item_sku tidak ditemukan.');
            if ($this->decimal($row, 'qty_invoiced') <= 0) $this->addMessage($errors, 'invoice_lines', $rowNo, 'qty_invoiced harus > 0.');
        }

        $paymentNos = [];
        foreach ($this->sheetRows($sheets, 'payment_headers') as $index => $row) {
            $rowNo = $index + 2;
            $paymentNo = $this->string($row, 'payment_no');
            if ($paymentNo === '') $this->addMessage($errors, 'payment_headers', $rowNo, 'payment_no wajib diisi.');
            if (isset($paymentNos[$paymentNo])) $this->addMessage($errors, 'payment_headers', $rowNo, "payment {$paymentNo} duplikat.");
            $paymentNos[$paymentNo] = true;
            if (! $this->vendorId($this->string($row, 'vendor_code'))) $this->addMessage($errors, 'payment_headers', $rowNo, 'vendor_code tidak ditemukan.');
            if ($paymentNo !== '' && DB::table('vendor_payments')->where('payment_no', $paymentNo)->exists()) $this->addMessage($errors, 'payment_headers', $rowNo, "payment_no {$paymentNo} sudah ada.");
        }

        $paymentByInvoice = [];
        foreach ($this->sheetRows($sheets, 'payment_lines') as $index => $row) {
            $rowNo = $index + 2;
            $paymentNo = $this->string($row, 'payment_no');
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            if (! isset($paymentNos[$paymentNo])) $this->addMessage($errors, 'payment_lines', $rowNo, 'payment_no tidak ditemukan di payment_headers.');
            if (! isset($invoiceNos[$invoiceNo])) $this->addMessage($errors, 'payment_lines', $rowNo, 'invoice_no_internal tidak ditemukan di invoice_headers.');
            $amount = $this->decimal($row, 'payment_amount') + $this->decimal($row, 'wht_amount');
            if ($amount <= 0) $this->addMessage($errors, 'payment_lines', $rowNo, 'payment_amount + wht_amount harus > 0.');
            $paymentByInvoice[$invoiceNo] = ($paymentByInvoice[$invoiceNo] ?? 0) + $amount;
        }

        foreach ($this->sheetRows($sheets, 'invoice_headers') as $index => $row) {
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            $net = $this->decimal($row, 'net_payable_amount') ?: $this->decimal($row, 'grand_total');
            if (($paymentByInvoice[$invoiceNo] ?? 0) > $net + 0.0001) {
                $this->addMessage($errors, 'invoice_headers', $index + 2, 'Total payment lines melebihi net payable invoice.');
            }
        }

        if (($summary['receiving_headers']['rows'] ?? 0) === 0) {
            $warnings[] = ['sheet' => 'receiving_headers', 'row' => null, 'message' => 'Tidak ada receiving; stock movement tidak akan dibuat.'];
        }

        return [$errors, $warnings, $summary];
    }

    private function commitPurchaseOrders(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'po_headers') as $row) {
            $poNo = $this->string($row, 'po_no');
            $vendorId = $this->vendorId($this->string($row, 'vendor_code'));
            $warehouseId = $this->warehouseId($this->string($row, 'warehouse_code'));
            $supplierId = $this->resolveSupplierId((int) $vendorId);
            $poDate = $this->date($row, 'po_date') ?: now()->toDateString();
            $status = $this->normalizePoStatus($this->string($row, 'status'));

            $poId = DB::table('purchase_orders')->insertGetId($this->filterColumns('purchase_orders', [
                'number' => $poNo,
                'po_number' => $poNo,
                'po_no' => $poNo,
                'company_id' => 1,
                'vendor_id' => $vendorId,
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'document_date' => $poDate,
                'po_date' => $poDate,
                'expected_date' => $this->date($row, 'expected_date'),
                'expected_delivery_date' => $this->date($row, 'expected_date'),
                'status' => $status,
                'approval_status' => in_array($status, ['approved', 'sent', 'completed', 'closed'], true) ? 'APPROVED' : 'PENDING',
                'fulfillment_status' => 'not_received',
                'currency' => $this->string($row, 'currency_code') ?: 'IDR',
                'currency_code' => $this->string($row, 'currency_code') ?: 'IDR',
                'exchange_rate' => $this->decimal($row, 'exchange_rate') ?: 1,
                'subtotal' => $this->decimal($row, 'subtotal'),
                'discount_total' => $this->decimal($row, 'discount_total'),
                'tax_total' => $this->decimal($row, 'tax_total'),
                'freight_total' => $this->decimal($row, 'freight_total'),
                'grand_total' => $this->decimal($row, 'grand_total'),
                'notes' => $this->string($row, 'notes') ?: null,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $context['po_ids'][$poNo] = $poId;
            $this->linkDocument($batch, 'purchase_order', $poNo, 'purchase_orders', $poId);
        }

        foreach ($this->sheetRows($sheets, 'po_lines') as $row) {
            $poNo = $this->string($row, 'po_no');
            $lineNo = $this->string($row, 'line_no');
            $itemId = (int) $this->itemId($this->string($row, 'item_sku'));
            $qty = $this->decimal($row, 'qty_ordered');
            $unitPrice = $this->decimal($row, 'unit_price');
            $lineTotal = $this->decimal($row, 'line_total') ?: ($qty * $unitPrice) - $this->decimal($row, 'discount_amount') + $this->decimal($row, 'tax_amount');
            $lineId = DB::table('purchase_order_items')->insertGetId($this->filterColumns('purchase_order_items', [
                'purchase_order_id' => $context['po_ids'][$poNo],
                'product_id' => $itemId,
                'product_name' => DB::table('items')->where('id', $itemId)->value('name'),
                'uom_id' => $this->uomId($this->string($row, 'uom_code')),
                'qty_ordered' => $qty,
                'qty_received' => 0,
                'received_qty' => 0,
                'remaining_qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_amount' => $this->decimal($row, 'discount_amount'),
                'tax_amount' => $this->decimal($row, 'tax_amount'),
                'line_total' => $lineTotal,
                'notes' => $this->string($row, 'notes') ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['po_line_ids'][$poNo.'#'.$lineNo] = $lineId;
            $this->linkDocument($batch, 'purchase_order_line', $poNo.'#'.$lineNo, 'purchase_order_items', $lineId);
        }
    }

    private function commitReceivings(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'receiving_headers') as $row) {
            $receivingNo = $this->string($row, 'receiving_no');
            $poNo = $this->string($row, 'po_no');
            $warehouseId = $this->warehouseId($this->string($row, 'warehouse_code'));
            $receiptDate = $this->date($row, 'receiving_date') ?: now()->toDateString();
            $entryId = DB::table('receiving_entries')->insertGetId($this->filterColumns('receiving_entries', [
                'number' => $receivingNo,
                'warehouse_id' => $warehouseId,
                'transaction_date' => $receiptDate,
                'transaction_code' => 'PEMBELIAN',
                'reference' => $poNo,
                'vendor_name' => DB::table('vendors')->where('id', $this->vendorId($this->string($row, 'vendor_code')))->value('name'),
                'vendor_id' => $this->vendorId($this->string($row, 'vendor_code')),
                'source_type' => 'purchase_order',
                'source_id' => $context['po_ids'][$poNo] ?? null,
                'notes' => $this->string($row, 'notes') ?: null,
                'total_value' => $this->decimal($row, 'total_value'),
                'status' => $this->normalizeReceivingStatus($this->string($row, 'status')),
                'posted_at' => strtolower($this->normalizeReceivingStatus($this->string($row, 'status'))) === 'posted' ? now() : null,
                'posted_by' => strtolower($this->normalizeReceivingStatus($this->string($row, 'status'))) === 'posted' ? $userId : null,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $context['receiving_ids'][$receivingNo] = $entryId;
            $this->linkDocument($batch, 'receiving_entry', $receivingNo, 'receiving_entries', $entryId);
        }

        $postedReceivingNos = collect($this->sheetRows($sheets, 'receiving_headers'))
            ->filter(fn (array $row): bool => strtolower($this->normalizeReceivingStatus($this->string($row, 'status'))) === 'posted')
            ->keyBy(fn (array $row): string => $this->string($row, 'receiving_no'));

        $lineSeq = [];
        foreach ($this->sheetRows($sheets, 'receiving_lines') as $row) {
            $receivingNo = $this->string($row, 'receiving_no');
            $lineNo = $this->string($row, 'line_no');
            $poLineKey = $this->string($row, 'po_no').'#'.$this->string($row, 'po_line_no');
            $poLine = DB::table('purchase_order_items')->where('id', $context['po_line_ids'][$poLineKey])->lockForUpdate()->first();
            $qty = $this->decimal($row, 'qty_received');
            $price = $this->decimal($row, 'unit_price');
            $previouslyReceived = (float) ($poLine->received_qty ?? $poLine->qty_received ?? 0);
            $remaining = max(0, (float) $poLine->qty_ordered - $previouslyReceived - $qty);
            $value = $this->decimal($row, 'line_value') ?: $qty * $price;
            $lineId = DB::table('receiving_entry_lines')->insertGetId($this->filterColumns('receiving_entry_lines', [
                'receiving_entry_id' => $context['receiving_ids'][$receivingNo],
                'source_item_id' => $poLine->id,
                'item_id' => $this->itemId($this->string($row, 'item_sku')),
                'uom_id' => $this->uomId($this->string($row, 'uom_code')),
                'qty' => $qty,
                'price' => $price,
                'previously_received_qty' => $previouslyReceived,
                'remaining_qty' => $remaining,
                'inventory_unit_cost' => $price,
                'inventory_total_cost' => $value,
                'value' => $value,
                'batch_number' => $this->string($row, 'batch_number') ?: null,
                'expired_date' => $this->date($row, 'expired_date'),
                'notes' => $this->string($row, 'notes') ?: null,
                'facility_scheme_id' => $poLine->facility_scheme_id ?? null,
                'facility_reference_no' => $poLine->facility_reference_no ?? null,
                'facility_reference_date' => $poLine->facility_reference_date ?? null,
                'facility_reference_note' => $poLine->facility_reference_note ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $context['receiving_line_ids'][$receivingNo.'#'.$lineNo] = $lineId;
            $this->linkDocument($batch, 'receiving_entry_line', $receivingNo.'#'.$lineNo, 'receiving_entry_lines', $lineId);
            $lineSeq[$receivingNo] = ($lineSeq[$receivingNo] ?? 0) + $value;

            if ($postedReceivingNos->has($receivingNo)) {
                $batchId = $this->resolveBatchId((int) $this->itemId($this->string($row, 'item_sku')), $this->string($row, 'batch_number'), $this->date($row, 'expired_date'));
                $qtyBase = $this->resolveQtyBase((int) $this->itemId($this->string($row, 'item_sku')), (int) $this->uomId($this->string($row, 'uom_code')), $qty, 0);
                $unitCostBase = $this->resolveUnitCostPerBase($price, $qty, $qtyBase);
                $headerRow = $postedReceivingNos->get($receivingNo);
                $this->stockService->postMutation([
                    'trx_type' => 'RCV_IN',
                    'trx_id' => $context['receiving_ids'][$receivingNo],
                    'trx_line_id' => $lineId,
                    'warehouse_id' => $this->warehouseId($this->string($headerRow, 'warehouse_code')),
                    'item_id' => $this->itemId($this->string($row, 'item_sku')),
                    'batch_id' => $batchId,
                    'qty_base' => $qtyBase,
                    'uom_id' => $this->uomId($this->string($row, 'uom_code')),
                    'qty_input' => $qty,
                    'unit_cost' => $unitCostBase,
                    'trx_datetime' => ($this->date($headerRow, 'receiving_date') ?: now()->toDateString()).' 00:00:00',
                    'created_by' => $userId,
                    'facility_scheme_id' => $poLine->facility_scheme_id ?? null,
                ]);

                $newReceived = $previouslyReceived + $qty;
                DB::table('purchase_order_items')->where('id', $poLine->id)->update($this->filterColumns('purchase_order_items', [
                    'received_qty' => $newReceived,
                    'qty_received' => $newReceived,
                    'remaining_qty' => max(0, (float) $poLine->qty_ordered - $newReceived),
                    'updated_at' => now(),
                ]));
            }
        }

        foreach ($lineSeq as $receivingNo => $totalValue) {
            DB::table('receiving_entries')->where('id', $context['receiving_ids'][$receivingNo])->update($this->filterColumns('receiving_entries', [
                'total_value' => round($totalValue, 6),
                'updated_at' => now(),
            ]));
        }
    }

    private function commitInvoices(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'invoice_headers') as $row) {
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            $grandTotal = $this->decimal($row, 'grand_total');
            $netPayable = $this->decimal($row, 'net_payable_amount') ?: $grandTotal;
            $paid = $this->decimal($row, 'paid_amount');
            $outstanding = $this->string($row, 'outstanding_amount') !== '' ? $this->decimal($row, 'outstanding_amount') : max(0, $netPayable - $paid);
            $status = $this->normalizeInvoiceStatus($this->string($row, 'status'));
            $invoiceId = DB::table('vendor_invoices')->insertGetId($this->filterColumns('vendor_invoices', [
                'company_id' => 1,
                'vendor_id' => $this->vendorId($this->string($row, 'vendor_code')),
                'invoice_no_internal' => $invoiceNo,
                'vendor_invoice_no' => $this->string($row, 'vendor_invoice_no') ?: $invoiceNo,
                'invoice_date' => $this->date($row, 'invoice_date') ?: now()->toDateString(),
                'due_date' => $this->date($row, 'due_date') ?: ($this->date($row, 'invoice_date') ?: now()->toDateString()),
                'currency_code' => $this->string($row, 'currency_code') ?: 'IDR',
                'exchange_rate' => $this->decimal($row, 'exchange_rate') ?: 1,
                'subtotal' => $this->decimal($row, 'subtotal'),
                'tax_amount' => $this->decimal($row, 'tax_amount'),
                'discount_amount' => $this->decimal($row, 'discount_amount'),
                'freight_amount' => $this->decimal($row, 'freight_amount'),
                'grand_total' => $grandTotal,
                'net_payable_amount' => $netPayable,
                'paid_amount' => $paid,
                'outstanding_amount' => $outstanding,
                'payment_status' => $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partial_paid' : 'unpaid'),
                'status' => $status,
                'posted_at' => in_array($status, ['POSTED', 'PARTIAL_PAID', 'PAID'], true) ? now() : null,
                'posted_by' => in_array($status, ['POSTED', 'PARTIAL_PAID', 'PAID'], true) ? $userId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $context['invoice_ids'][$invoiceNo] = $invoiceId;
            $this->linkDocument($batch, 'vendor_invoice', $invoiceNo, 'vendor_invoices', $invoiceId);

            if (in_array($status, ['POSTED', 'PARTIAL_PAID', 'PAID'], true)) {
                $this->insertVendorLedger((int) $this->vendorId($this->string($row, 'vendor_code')), $this->date($row, 'invoice_date') ?: now()->toDateString(), 'vendor_invoice', $invoiceId, $invoiceNo, $netPayable, 0, $status);
            }
        }

        foreach ($this->sheetRows($sheets, 'invoice_lines') as $row) {
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            $lineId = DB::table('vendor_invoice_lines')->insertGetId($this->filterColumns('vendor_invoice_lines', [
                'vendor_invoice_id' => $context['invoice_ids'][$invoiceNo],
                'receiving_entry_line_id' => $context['receiving_line_ids'][$this->string($row, 'receiving_no').'#'.$this->string($row, 'receiving_line_no')] ?? null,
                'item_id' => $this->itemId($this->string($row, 'item_sku')),
                'description' => $this->string($row, 'description') ?: null,
                'qty_invoiced' => $this->decimal($row, 'qty_invoiced'),
                'unit_price' => $this->decimal($row, 'unit_price'),
                'tax_amount' => $this->decimal($row, 'tax_amount'),
                'line_total' => $this->decimal($row, 'line_total'),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $this->linkDocument($batch, 'vendor_invoice_line', $invoiceNo.'#'.$this->string($row, 'line_no'), 'vendor_invoice_lines', $lineId);
        }
    }

    private function commitPayments(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'payment_headers') as $row) {
            $paymentNo = $this->string($row, 'payment_no');
            $status = $this->normalizePaymentStatus($this->string($row, 'status'));
            $paymentId = DB::table('vendor_payments')->insertGetId($this->filterColumns('vendor_payments', [
                'company_id' => 1,
                'vendor_id' => $this->vendorId($this->string($row, 'vendor_code')),
                'payment_no' => $paymentNo,
                'payment_number' => $paymentNo,
                'payment_date' => $this->date($row, 'payment_date') ?: now()->toDateString(),
                'payment_method' => strtoupper($this->string($row, 'payment_method') ?: 'BANK_TRANSFER'),
                'currency_code' => $this->string($row, 'currency_code') ?: 'IDR',
                'currency' => $this->string($row, 'currency_code') ?: 'IDR',
                'exchange_rate' => $this->decimal($row, 'exchange_rate') ?: 1,
                'total_amount' => $this->decimal($row, 'total_amount'),
                'allocated_amount' => $this->decimal($row, 'allocated_amount'),
                'unapplied_amount' => $this->decimal($row, 'unapplied_amount'),
                'status' => $status,
                'total_invoice_amount' => $this->decimal($row, 'total_invoice_amount'),
                'total_wht_amount' => $this->decimal($row, 'total_wht_amount'),
                'stamp_duty_amount' => $this->decimal($row, 'stamp_duty_amount'),
                'freight_amount' => $this->decimal($row, 'freight_amount'),
                'bank_charge_amount' => $this->decimal($row, 'bank_charge_amount'),
                'total_additional_cost' => $this->decimal($row, 'total_additional_cost'),
                'net_vendor_payment_amount' => $this->decimal($row, 'net_vendor_payment_amount'),
                'total_cash_out_amount' => $this->decimal($row, 'total_cash_out_amount'),
                'notes' => $this->string($row, 'notes') ?: null,
                'posted_at' => $status === 'POSTED' ? now() : null,
                'posted_by' => $status === 'POSTED' ? $userId : null,
                'paid_at' => in_array($status, ['PAID', 'POSTED'], true) ? now() : null,
                'paid_by' => in_array($status, ['PAID', 'POSTED'], true) ? $userId : null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['payment_ids'][$paymentNo] = $paymentId;
            $this->linkDocument($batch, 'vendor_payment', $paymentNo, 'vendor_payments', $paymentId);
        }

        $paymentTotals = [];
        $invoicePayments = [];
        foreach ($this->sheetRows($sheets, 'payment_lines') as $row) {
            $paymentNo = $this->string($row, 'payment_no');
            $invoiceNo = $this->string($row, 'invoice_no_internal');
            $invoice = DB::table('vendor_invoices')->where('id', $context['invoice_ids'][$invoiceNo])->lockForUpdate()->first();
            $paymentAmount = $this->decimal($row, 'payment_amount');
            $whtAmount = $this->decimal($row, 'wht_amount');
            $lineId = DB::table('vendor_payment_lines')->insertGetId($this->filterColumns('vendor_payment_lines', [
                'vendor_payment_id' => $context['payment_ids'][$paymentNo],
                'vendor_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->vendor_invoice_no ?: $invoice->invoice_no_internal,
                'invoice_date' => $invoice->invoice_date,
                'invoice_total_amount' => $invoice->net_payable_amount ?? $invoice->grand_total,
                'invoice_outstanding_amount' => $invoice->outstanding_amount,
                'payment_amount' => $paymentAmount,
                'wht_amount' => $whtAmount,
                'net_payment_amount' => $paymentAmount - $whtAmount,
                'notes' => $this->string($row, 'notes') ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            if (Schema::hasTable('vendor_payment_allocations')) {
                DB::table('vendor_payment_allocations')->insert($this->filterColumns('vendor_payment_allocations', [
                    'vendor_payment_id' => $context['payment_ids'][$paymentNo],
                    'vendor_invoice_id' => $invoice->id,
                    'allocated_amount' => $paymentAmount + $whtAmount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
            $this->linkDocument($batch, 'vendor_payment_line', $paymentNo.'#'.$this->string($row, 'line_no'), 'vendor_payment_lines', $lineId);
            $paymentTotals[$paymentNo] = ($paymentTotals[$paymentNo] ?? 0) + $paymentAmount + $whtAmount;
            $invoicePayments[$invoiceNo] = ($invoicePayments[$invoiceNo] ?? 0) + $paymentAmount + $whtAmount;
        }

        foreach ($paymentTotals as $paymentNo => $total) {
            DB::table('vendor_payments')->where('id', $context['payment_ids'][$paymentNo])->update($this->filterColumns('vendor_payments', [
                'total_amount' => $total,
                'allocated_amount' => $total,
                'total_invoice_amount' => $total,
                'updated_at' => now(),
            ]));
            $payment = DB::table('vendor_payments')->where('id', $context['payment_ids'][$paymentNo])->first();
            if (in_array((string) $payment->status, ['APPROVED', 'PAID', 'POSTED'], true)) {
                $this->insertVendorLedger((int) $payment->vendor_id, (string) $payment->payment_date, 'vendor_payment', (int) $payment->id, $paymentNo, 0, $total, (string) $payment->status);
            }
        }

        foreach ($invoicePayments as $invoiceNo => $totalPaid) {
            $invoice = DB::table('vendor_invoices')->where('id', $context['invoice_ids'][$invoiceNo])->lockForUpdate()->first();
            $net = (float) ($invoice->net_payable_amount ?? $invoice->grand_total ?? 0);
            $paid = min($net, (float) ($invoice->paid_amount ?? 0) + $totalPaid);
            $outstanding = max(0, $net - $paid);
            DB::table('vendor_invoices')->where('id', $invoice->id)->update($this->filterColumns('vendor_invoices', [
                'paid_amount' => $paid,
                'outstanding_amount' => $outstanding,
                'status' => $outstanding <= 0 ? 'PAID' : ($paid > 0 ? 'PARTIAL_PAID' : $invoice->status),
                'payment_status' => $outstanding <= 0 ? 'paid' : ($paid > 0 ? 'partial_paid' : 'unpaid'),
                'updated_at' => now(),
            ]));
        }
    }

    private function insertVendorLedger(int $vendorId, string $date, string $type, int $id, string $description, float $debit, float $credit, string $status): void
    {
        if (! Schema::hasTable('vendor_ledgers')) {
            return;
        }

        DB::table('vendor_ledgers')->insert($this->filterColumns('vendor_ledgers', [
            'vendor_id' => $vendorId,
            'transaction_date' => $date,
            'reference_type' => $type,
            'reference_id' => $id,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'balance' => 0,
            'status' => strtolower($status),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function refreshPurchaseOrderFulfillment(array $poIds): void
    {
        foreach ($poIds as $poId) {
            $items = DB::table('purchase_order_items')->where('purchase_order_id', $poId)->get();
            if ($items->isEmpty()) continue;
            $hasReceived = $items->contains(fn (object $item): bool => (float) ($item->received_qty ?? $item->qty_received ?? 0) > 0);
            $allDone = $items->every(fn (object $item): bool => ((float) ($item->remaining_qty ?? ((float) $item->qty_ordered - (float) ($item->received_qty ?? $item->qty_received ?? 0))) <= 0) || (bool) ($item->is_closed ?? false));
            DB::table('purchase_orders')->where('id', $poId)->update($this->filterColumns('purchase_orders', [
                'fulfillment_status' => $allDone ? 'fully_received' : ($hasReceived ? 'partially_received' : 'not_received'),
                'updated_at' => now(),
            ]));
        }
    }

    private function linkDocument(object $batch, string $type, string $no, string $table, int $id): void
    {
        DB::table('manual_purchase_integration_document_links')->insert([
            'batch_id' => $batch->id,
            'source_system' => $batch->source_system,
            'source_branch_code' => $batch->source_branch_code,
            'document_type' => $type,
            'document_no' => $no,
            'target_table' => $table,
            'target_id' => $id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function templateSheets(): array
    {
        return [
            'README' => [
                ['manual_purchase_integration_template', 'v1'],
                ['Catatan', 'Master vendor/item/uom/warehouse harus sudah ada. Receiving masuk receiving_entries dan stock ledger RCV_IN. AP masuk vendor invoice/payment/ledger. Tidak membuat jurnal akunting.'],
            ],
            'po_headers' => [
                ['po_no', 'po_date', 'expected_date', 'vendor_code', 'warehouse_code', 'currency_code', 'exchange_rate', 'status', 'subtotal', 'discount_total', 'tax_total', 'freight_total', 'grand_total', 'notes'],
                ['PO-LAMA-001', '2026-01-05', '2026-01-10', 'VND-001', 'WH-UTAMA', 'IDR', '1', 'approved', '1000000', '0', '110000', '0', '1110000', 'Contoh PO historical'],
            ],
            'po_lines' => [
                ['po_no', 'line_no', 'item_sku', 'uom_code', 'qty_ordered', 'unit_price', 'discount_amount', 'tax_amount', 'line_total', 'notes'],
                ['PO-LAMA-001', '1', 'SKU-001', 'PCS', '10', '100000', '0', '110000', '1110000', 'Contoh line PO'],
            ],
            'receiving_headers' => [
                ['receiving_no', 'receiving_date', 'po_no', 'vendor_code', 'warehouse_code', 'status', 'total_value', 'notes'],
                ['RCV-LAMA-001', '2026-01-08', 'PO-LAMA-001', 'VND-001', 'WH-UTAMA', 'posted', '1000000', 'Contoh receiving posted'],
            ],
            'receiving_lines' => [
                ['receiving_no', 'line_no', 'po_no', 'po_line_no', 'item_sku', 'uom_code', 'qty_received', 'unit_price', 'line_value', 'batch_number', 'expired_date', 'notes'],
                ['RCV-LAMA-001', '1', 'PO-LAMA-001', '1', 'SKU-001', 'PCS', '10', '100000', '1000000', 'BATCH-001', '2027-01-31', 'Contoh receiving line'],
            ],
            'invoice_headers' => [
                ['invoice_no_internal', 'vendor_invoice_no', 'invoice_date', 'due_date', 'vendor_code', 'currency_code', 'exchange_rate', 'status', 'subtotal', 'tax_amount', 'discount_amount', 'freight_amount', 'grand_total', 'net_payable_amount', 'paid_amount', 'outstanding_amount', 'notes'],
                ['VI-LAMA-001', 'SUP-INV-001', '2026-01-09', '2026-02-08', 'VND-001', 'IDR', '1', 'PAID', '1000000', '110000', '0', '0', '1110000', '1110000', '0', '1110000', 'Contoh invoice'],
            ],
            'invoice_lines' => [
                ['invoice_no_internal', 'line_no', 'receiving_no', 'receiving_line_no', 'item_sku', 'description', 'qty_invoiced', 'unit_price', 'tax_amount', 'line_total'],
                ['VI-LAMA-001', '1', 'RCV-LAMA-001', '1', 'SKU-001', 'Invoice dari receiving', '10', '100000', '110000', '1110000'],
            ],
            'payment_headers' => [
                ['payment_no', 'payment_date', 'vendor_code', 'payment_method', 'currency_code', 'exchange_rate', 'status', 'total_amount', 'allocated_amount', 'unapplied_amount', 'notes'],
                ['PAY-LAMA-001', '2026-01-20', 'VND-001', 'BANK_TRANSFER', 'IDR', '1', 'POSTED', '1110000', '1110000', '0', 'Contoh payment'],
            ],
            'payment_lines' => [
                ['payment_no', 'line_no', 'invoice_no_internal', 'payment_amount', 'wht_amount', 'net_payment_amount', 'notes'],
                ['PAY-LAMA-001', '1', 'VI-LAMA-001', '1110000', '0', '1110000', 'Contoh alokasi payment'],
            ],
        ];
    }

    private function sheetRows(array $sheets, string $sheet): array
    {
        return array_values(array_filter($sheets[$sheet] ?? [], fn (array $row): bool => ! $this->isRowEmpty($row)));
    }

    private function parseWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];

        $sharedStrings = $this->readSharedStrings($zip);
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $relationshipTargets = [];
        if ($rels !== false) {
            foreach ($rels->Relationship as $rel) {
                $relationshipTargets[(string) $rel['Id']] = (string) $rel['Target'];
            }
        }

        $sheets = [];
        if ($workbook !== false && isset($workbook->sheets->sheet)) {
            foreach ($workbook->sheets->sheet as $sheet) {
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string) ($attrs['id'] ?? '');
                $target = $relationshipTargets[$rid] ?? '';
                if ($target === '') continue;
                $sheetName = (string) $sheet['name'];
                $sheetXml = $zip->getFromName('xl/'.ltrim($target, '/')) ?: $zip->getFromName('xl/worksheets/'.basename($target));
                if (! $sheetXml) continue;
                $sheets[$sheetName] = $this->parseSheetXml($sheetXml, $sharedStrings);
            }
        }
        $zip->close();

        return $sheets;
    }

    private function parseSheetXml(string $sheetXml, array $sharedStrings): array
    {
        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData->row)) return [];

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $ref = (string) ($cell['r'] ?? 'A1');
                preg_match('/([A-Z]+)/', $ref, $match);
                $index = $this->xlsxColumnToIndex($match[1] ?? 'A');
                $type = (string) ($cell['t'] ?? '');
                if ($type === 's') {
                    $value = $sharedStrings[(int) ($cell->v ?? 0)] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }
                $line[$index] = trim($value);
            }
            if ($line !== []) {
                ksort($line);
                $table[] = $line;
            }
        }
        if (count($table) < 1) return [];
        $header = array_map(fn ($v): string => trim((string) $v), $table[0]);
        $rows = [];
        foreach (array_slice($table, 1) as $line) {
            $rows[] = collect($header)->mapWithKeys(fn ($key, $index): array => [$key => trim((string) ($line[$index] ?? ''))])->all();
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $strings = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if (! $xml) return $strings;
        $shared = simplexml_load_string($xml);
        if ($shared !== false && isset($shared->si)) {
            foreach ($shared->si as $si) {
                if (isset($si->t)) {
                    $strings[] = (string) $si->t;
                } else {
                    $text = '';
                    foreach ($si->r ?? [] as $run) $text .= (string) ($run->t ?? '');
                    $strings[] = $text;
                }
            }
        }
        return $strings;
    }

    private function buildTemplateXlsx(string $path, array $sheets): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return;

        $contentTypes = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $workbookSheets = '';
        $rels = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $index = 1;
        foreach ($sheets as $name => $rows) {
            $sheetPath = "xl/worksheets/sheet{$index}.xml";
            $zip->addFromString($sheetPath, $this->sheetXml($rows));
            $contentTypes .= "<Override PartName=\"/{$sheetPath}\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $escapedName = htmlspecialchars((string) $name, ENT_XML1);
            $workbookSheets .= "<sheet name=\"{$escapedName}\" sheetId=\"{$index}\" r:id=\"rId{$index}\"/>";
            $rels .= "<Relationship Id=\"rId{$index}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$index}.xml\"/>";
            $index++;
        }
        $contentTypes .= '</Types>';
        $rels .= '</Relationships>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbookSheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->close();
    }

    private function sheetXml(array $rows): string
    {
        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $colIndex => $value) {
                $column = $this->indexToXlsxColumn($colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cellXml.'</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>';
    }

    private function requireHeaders(array $sheets, string $sheet, array $headers, array &$errors): void
    {
        if (! array_key_exists($sheet, $sheets)) {
            $this->addMessage($errors, $sheet, null, "Sheet {$sheet} tidak ditemukan.");
            return;
        }
        $first = $sheets[$sheet][0] ?? [];
        foreach ($headers as $header) {
            if (! array_key_exists($header, $first)) $this->addMessage($errors, $sheet, 1, "Header {$header} wajib ada.");
        }
    }

    private function addMessage(array &$messages, string $sheet, ?int $row, string $message): void
    {
        $messages[] = ['sheet' => $sheet, 'row' => $row, 'message' => $message];
    }

    private function rowHasError(array $errors, string $sheet, int $row): bool
    {
        return collect($errors)->contains(fn (array $error): bool => $error['sheet'] === $sheet && (int) ($error['row'] ?? 0) === $row);
    }

    private function messagesForRow(array $errors, array $warnings, string $sheet, int $row): array
    {
        return collect($errors)->concat($warnings)->filter(fn (array $message): bool => $message['sheet'] === $sheet && (int) ($message['row'] ?? 0) === $row)->values()->all();
    }

    private function string(array $row, string $key): string
    {
        return trim((string) ($row[$key] ?? ''));
    }

    private function decimal(array $row, string $key): float
    {
        $value = str_replace([',', ' '], ['', ''], $this->string($row, $key));
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function date(array $row, string $key): ?string
    {
        $value = $this->string($row, $key);
        if ($value === '') return null;
        if (is_numeric($value)) return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString();
        try { return Carbon::parse($value)->toDateString(); } catch (\Throwable) { return null; }
    }

    private function vendorId(string $code): ?int
    {
        return $code === '' ? null : DB::table('vendors')->where('vendor_code', $code)->value('id');
    }

    private function warehouseId(string $code): ?int
    {
        return $code === '' ? null : DB::table('warehouses')->where('code', $code)->value('id');
    }

    private function itemId(string $sku): ?int
    {
        return $sku === '' ? null : DB::table('items')->where('sku', $sku)->value('id');
    }

    private function uomId(string $code): ?int
    {
        return $code === '' ? null : DB::table('uoms')->where('code', $code)->value('id');
    }

    private function purchaseOrderExists(string $number): bool
    {
        return DB::table('purchase_orders')
            ->where('number', $number)
            ->orWhere('po_number', $number)
            ->when(Schema::hasColumn('purchase_orders', 'po_no'), fn ($q) => $q->orWhere('po_no', $number))
            ->exists();
    }

    private function documentLinkExists(string $sourceSystem, string $sourceBranchCode, string $type, string $no): bool
    {
        return DB::table('manual_purchase_integration_document_links')
            ->where('source_system', $sourceSystem)
            ->where('source_branch_code', $sourceBranchCode)
            ->where('document_type', $type)
            ->where('document_no', $no)
            ->exists();
    }

    private function resolveSupplierId(int $vendorId): int
    {
        $vendor = DB::table('vendors')->where('id', $vendorId)->first();
        $supplierId = DB::table('suppliers')->where('code', $vendor->vendor_code)->value('id') ?: DB::table('suppliers')->where('id', $vendorId)->value('id');
        if (! $supplierId) {
            $supplierId = DB::table('suppliers')->insertGetId([
                'code' => $vendor->vendor_code ?: ('VENDOR-'.$vendor->id),
                'name' => $vendor->name ?: ('Vendor #'.$vendor->id),
                'phone' => $vendor->phone ?? null,
                'email' => $vendor->email ?? null,
                'address' => $vendor->address ?? null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        return (int) $supplierId;
    }

    private function resolveBatchId(int $itemId, string $batchNumber, ?string $expiredDate): ?int
    {
        $batchNumber = trim($batchNumber);
        if ($batchNumber === '') return null;
        $query = DB::table('item_batches')->where('item_id', $itemId)->where('batch_no', $batchNumber);
        if ($expiredDate) $query->where('expired_date', $expiredDate);
        $id = $query->value('id');
        if ($id) return (int) $id;

        return DB::table('item_batches')->insertGetId([
            'item_id' => $itemId,
            'batch_no' => $batchNumber,
            'expired_date' => $expiredDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizePoStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, ['draft', 'pending_approval', 'approved', 'sent', 'completed', 'cancelled', 'closed'], true) ? $status : 'approved';
    }

    private function normalizeReceivingStatus(string $status): string
    {
        return strtolower(trim($status)) === 'posted' ? 'posted' : (trim($status) ?: 'DRAFT');
    }

    private function normalizeInvoiceStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        return in_array($status, ['DRAFT', 'POSTED', 'PARTIAL_PAID', 'PAID', 'VOID'], true) ? $status : 'POSTED';
    }

    private function normalizePaymentStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        return in_array($status, ['DRAFT', 'SUBMITTED', 'APPROVED', 'PAID', 'POSTED', 'VOID'], true) ? $status : 'POSTED';
    }

    private function filterColumns(string $table, array $payload): array
    {
        $validColumns = array_flip(Schema::getColumnListing($table));
        return array_filter($payload, fn (string $column): bool => isset($validColumns[$column]), ARRAY_FILTER_USE_KEY);
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) if (trim((string) $value) !== '') return false;
        return true;
    }

    private function xlsxColumnToIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $char) $index = $index * 26 + (ord($char) - 64);
        return $index - 1;
    }

    private function indexToXlsxColumn(int $index): string
    {
        $column = '';
        $index++;
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $column = chr(65 + $remainder).$column;
            $index = intdiv($index - 1, 26);
        }
        return $column;
    }

    private function resolveQtyBase(int $itemId, int $uomId, float $qtyInput, float $qtyBase): float
    {
        if ($qtyBase !== 0.0) return $qtyBase;
        return $this->uomConversionService->toBase($itemId, $uomId, $qtyInput);
    }

    private function resolveUnitCostPerBase(float $unitCostInput, float $qtyInput, float $qtyBase): float
    {
        if ($qtyInput == 0.0) return $unitCostInput;
        $conversionFactor = abs($qtyBase / $qtyInput);
        return $conversionFactor <= 0.0 ? $unitCostInput : $unitCostInput / $conversionFactor;
    }
}
