<?php

namespace App\Http\Controllers\Apps\Setup;

use App\Http\Controllers\Controller;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ManualSalesIntegrationController extends Controller
{
    private const SHEETS = [
        'so_headers',
        'so_lines',
        'fulfillment_headers',
        'fulfillment_lines',
        'invoice_headers',
        'invoice_lines',
        'collection_headers',
        'collection_lines',
    ];

    public function __construct(private readonly UomConversionService $uomConversionService)
    {
    }

    public function index(): Response
    {
        $batches = DB::table('manual_sales_integration_batches')
            ->orderByDesc('id')
            ->paginate(10)
            ->through(function (object $batch): object {
                $batch->summary = json_decode((string) ($batch->summary_json ?? '{}'), true) ?: [];
                $batch->errors = array_slice(json_decode((string) ($batch->errors_json ?? '[]'), true) ?: [], 0, 10);
                $batch->warnings = array_slice(json_decode((string) ($batch->warnings_json ?? '[]'), true) ?: [], 0, 10);

                return $batch;
            });

        return Inertia::render('Setup/ManualSalesIntegration/Index', [
            'batches' => $batches,
            'purposes' => ['INITIAL_HISTORY', 'BRANCH_INTEGRATION', 'MANUAL_BACKFILL', 'CORRECTION'],
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/manual-sales-integration-template-'.now()->format('YmdHis').'.xlsx');
        $this->buildTemplateXlsx($path, $this->templateSheets());

        return response()->download($path, 'manual-sales-integration-template.xlsx')->deleteFileAfterSend(true);
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
            $batchId = DB::table('manual_sales_integration_batches')->insertGetId([
                'batch_no' => 'MSI-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
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

            $this->insertPreviewRows($batchId, $sheets, $errors, $warnings);

            return $batchId;
        });

        $message = count($errors) > 0
            ? 'Import template tersimpan sebagai batch validasi gagal. Perbaiki error sebelum commit.'
            : 'Import template berhasil divalidasi. Silakan review lalu commit.';

        return to_route('apps.setup.manual-sales-integration.index')
            ->with(count($errors) > 0 ? 'error' : 'success', $message)
            ->with('batch_id', $batchId);
    }

    public function retry(Request $request, int $batch): RedirectResponse
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

        DB::transaction(function () use ($batch, $validated, $file, $sheets, $errors, $warnings, $summary, $request): void {
            $header = DB::table('manual_sales_integration_batches')->where('id', $batch)->lockForUpdate()->first();
            abort_unless($header, 404);
            abort_if($header->status !== 'validation_failed', 422, 'Hanya batch validation_failed yang bisa diperbaiki dengan upload ulang.');

            DB::table('manual_sales_integration_rows')->where('batch_id', $batch)->delete();
            DB::table('manual_sales_integration_batches')->where('id', $batch)->update([
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
                'updated_at' => now(),
            ]);

            $this->insertPreviewRows($batch, $sheets, $errors, $warnings);
        });

        $message = count($errors) > 0
            ? 'Upload perbaikan masih memiliki error validasi. Silakan perbaiki data dan upload ulang, atau hapus batch jika tidak digunakan.'
            : 'Upload perbaikan berhasil divalidasi. Batch siap di-review dan commit.';

        return to_route('apps.setup.manual-sales-integration.index')
            ->with(count($errors) > 0 ? 'error' : 'success', $message)
            ->with('batch_id', $batch);
    }

    public function discard(int $batch): RedirectResponse
    {
        $this->deleteValidationFailedBatch($batch);

        return to_route('apps.setup.manual-sales-integration.index')
            ->with('success', 'Batch validation failed berhasil dihapus.');
    }

    public function destroy(int $batch): RedirectResponse
    {
        return $this->discard($batch);
    }

    public function show(int $batch): JsonResponse
    {
        $header = DB::table('manual_sales_integration_batches')->where('id', $batch)->first();
        abort_unless($header, 404);

        return response()->json([
            'batch' => $header,
            'rows' => DB::table('manual_sales_integration_rows')->where('batch_id', $batch)->orderBy('sheet_name')->orderBy('row_number')->limit(500)->get(),
            'links' => DB::table('manual_sales_integration_document_links')->where('batch_id', $batch)->orderBy('id')->get(),
        ]);
    }

    public function commit(Request $request, int $batch): RedirectResponse
    {
        $header = DB::table('manual_sales_integration_batches')->where('id', $batch)->lockForUpdate()->first();
        abort_unless($header, 404);
        abort_if($header->status === 'committed', 422, 'Batch sudah committed.');
        abort_if($header->status !== 'validated', 422, 'Batch belum valid, tidak bisa commit.');

        $sheets = json_decode((string) $header->preview_json, true) ?: [];
        [$errors] = $this->validateWorkbook($sheets, (string) $header->source_system, (string) $header->source_branch_code);
        abort_if(count($errors) > 0, 422, 'Batch memiliki error validasi terbaru, upload ulang file yang sudah diperbaiki.');

        DB::transaction(function () use ($header, $sheets, $request): void {
            $context = ['so_ids' => [], 'so_line_ids' => [], 'shipment_ids' => [], 'shipment_line_ids' => [], 'invoice_ids' => [], 'payment_ids' => []];
            $this->commitSalesOrders($sheets, $header, $request->user()?->id, $context);
            $this->commitFulfillments($sheets, $header, $request->user()?->id, $context);
            $this->commitInvoices($sheets, $header, $request->user()?->id, $context);
            $this->commitCollections($sheets, $header, $request->user()?->id, $context);
            $this->refreshSalesOrderFulfillment($context['so_ids']);

            DB::table('manual_sales_integration_batches')->where('id', $header->id)->update([
                'status' => 'committed',
                'committed_by' => $request->user()?->id,
                'committed_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return to_route('apps.setup.manual-sales-integration.index')
            ->with('success', 'Batch manual sales integration berhasil committed.');
    }

    private function deleteValidationFailedBatch(int $batch): void
    {
        DB::transaction(function () use ($batch): void {
            $header = DB::table('manual_sales_integration_batches')->where('id', $batch)->lockForUpdate()->first();
            abort_unless($header, 404);
            abort_if($header->status !== 'validation_failed', 422, 'Hanya batch validation_failed yang bisa dihapus dari action ini.');
            DB::table('manual_sales_integration_document_links')->where('batch_id', $batch)->delete();
            DB::table('manual_sales_integration_rows')->where('batch_id', $batch)->delete();
            DB::table('manual_sales_integration_batches')->where('id', $batch)->delete();
        });
    }

    private function validateWorkbook(array $sheets, string $sourceSystem, string $sourceBranchCode): array
    {
        $errors = [];
        $warnings = [];
        $summary = [];
        foreach (self::SHEETS as $sheet) {
            $rows = collect($sheets[$sheet] ?? [])->filter(fn (array $row): bool => ! $this->isRowEmpty($row))->values();
            $summary[$sheet] = ['rows' => $rows->count()];
        }

        $this->requireHeaders($sheets, 'so_headers', ['so_no', 'so_date', 'customer_code', 'warehouse_code', 'status'], $errors);
        $this->requireHeaders($sheets, 'so_lines', ['so_no', 'line_no', 'item_sku', 'uom_code', 'qty_ordered', 'unit_price'], $errors);

        $hasFulfillmentHeaders = ($summary['fulfillment_headers']['rows'] ?? 0) > 0;
        $hasFulfillmentLines = ($summary['fulfillment_lines']['rows'] ?? 0) > 0;
        $hasInvoiceHeaders = ($summary['invoice_headers']['rows'] ?? 0) > 0;
        $hasInvoiceLines = ($summary['invoice_lines']['rows'] ?? 0) > 0;
        $hasCollectionHeaders = ($summary['collection_headers']['rows'] ?? 0) > 0;
        $hasCollectionLines = ($summary['collection_lines']['rows'] ?? 0) > 0;

        if ($hasFulfillmentHeaders || $hasFulfillmentLines) {
            $this->requireHeaders($sheets, 'fulfillment_headers', ['fulfillment_no', 'fulfillment_date', 'so_no', 'customer_code', 'warehouse_code', 'status'], $errors);
            $this->requireHeaders($sheets, 'fulfillment_lines', ['fulfillment_no', 'line_no', 'so_no', 'so_line_no', 'item_sku', 'uom_code', 'qty_fulfilled'], $errors);
            if (! $hasFulfillmentHeaders) $this->addMessage($errors, 'fulfillment_headers', null, 'fulfillment_headers wajib diisi jika fulfillment_lines diisi.');
            if (! $hasFulfillmentLines) $this->addMessage($errors, 'fulfillment_lines', null, 'fulfillment_lines wajib diisi jika fulfillment_headers diisi.');
        }

        if ($hasInvoiceHeaders || $hasInvoiceLines) {
            $this->requireHeaders($sheets, 'invoice_headers', ['invoice_no', 'invoice_date', 'due_date', 'customer_code', 'status', 'grand_total'], $errors);
            $this->requireHeaders($sheets, 'invoice_lines', ['invoice_no', 'line_no', 'item_sku', 'qty_invoiced', 'unit_price', 'line_total'], $errors);
            if (! $hasInvoiceHeaders) $this->addMessage($errors, 'invoice_headers', null, 'invoice_headers wajib diisi jika invoice_lines diisi.');
            if (! $hasInvoiceLines) $this->addMessage($errors, 'invoice_lines', null, 'invoice_lines wajib diisi jika invoice_headers diisi.');
        }

        if ($hasCollectionHeaders || $hasCollectionLines) {
            $this->requireHeaders($sheets, 'collection_headers', ['collection_no', 'collection_date', 'customer_code', 'payment_method', 'status'], $errors);
            $this->requireHeaders($sheets, 'collection_lines', ['collection_no', 'line_no', 'invoice_no', 'collection_amount'], $errors);
            if (! $hasCollectionHeaders) $this->addMessage($errors, 'collection_headers', null, 'collection_headers wajib diisi jika collection_lines diisi.');
            if (! $hasCollectionLines) $this->addMessage($errors, 'collection_lines', null, 'collection_lines wajib diisi jika collection_headers diisi.');
        }

        $soNos = [];
        foreach ($this->sheetRows($sheets, 'so_headers') as $index => $row) {
            $rowNo = $index + 2;
            $soNo = $this->string($row, 'so_no');
            if ($soNo === '') $this->addMessage($errors, 'so_headers', $rowNo, 'so_no wajib diisi.');
            if (isset($soNos[$soNo])) $this->addMessage($errors, 'so_headers', $rowNo, "so_no {$soNo} duplikat di file.");
            $soNos[$soNo] = true;
            if (! $this->customerId($this->string($row, 'customer_code'))) $this->addMessage($errors, 'so_headers', $rowNo, 'customer_code tidak ditemukan di master customer.');
            if (! $this->warehouseId($this->string($row, 'warehouse_code'))) $this->addMessage($errors, 'so_headers', $rowNo, 'warehouse_code tidak ditemukan di master warehouse.');
            if ($soNo !== '' && DB::table('sales')->where('number', $soNo)->exists()) $this->addMessage($errors, 'so_headers', $rowNo, "SO {$soNo} sudah ada.");
            if ($soNo !== '' && $this->documentLinkExists($sourceSystem, $sourceBranchCode, 'sales_order', $soNo)) $this->addMessage($errors, 'so_headers', $rowNo, "SO {$soNo} sudah pernah di-commit dari source yang sama.");
        }

        $soLineKeys = [];
        foreach ($this->sheetRows($sheets, 'so_lines') as $index => $row) {
            $rowNo = $index + 2;
            $soNo = $this->string($row, 'so_no');
            $lineNo = $this->string($row, 'line_no');
            if (! isset($soNos[$soNo])) $this->addMessage($errors, 'so_lines', $rowNo, 'so_no tidak ditemukan di so_headers.');
            if ($lineNo === '') $this->addMessage($errors, 'so_lines', $rowNo, 'line_no wajib diisi.');
            $key = $soNo.'#'.$lineNo;
            if (isset($soLineKeys[$key])) $this->addMessage($errors, 'so_lines', $rowNo, "Line SO {$key} duplikat.");
            $soLineKeys[$key] = true;
            if (! $this->itemId($this->string($row, 'item_sku'))) $this->addMessage($errors, 'so_lines', $rowNo, 'item_sku tidak ditemukan.');
            if (! $this->uomId($this->string($row, 'uom_code'))) $this->addMessage($errors, 'so_lines', $rowNo, 'uom_code tidak ditemukan.');
            if ($this->decimal($row, 'qty_ordered') <= 0) $this->addMessage($errors, 'so_lines', $rowNo, 'qty_ordered harus > 0.');
        }

        $fulfillmentNos = [];
        $fulfillmentLineKeys = [];
        foreach ($this->sheetRows($sheets, 'fulfillment_headers') as $index => $row) {
            $rowNo = $index + 2;
            $fulfillmentNo = $this->string($row, 'fulfillment_no');
            $soNo = $this->string($row, 'so_no');
            if ($fulfillmentNo === '') $this->addMessage($errors, 'fulfillment_headers', $rowNo, 'fulfillment_no wajib diisi.');
            if (isset($fulfillmentNos[$fulfillmentNo])) $this->addMessage($errors, 'fulfillment_headers', $rowNo, "fulfillment {$fulfillmentNo} duplikat.");
            $fulfillmentNos[$fulfillmentNo] = true;
            if (! isset($soNos[$soNo])) $this->addMessage($errors, 'fulfillment_headers', $rowNo, 'so_no tidak ditemukan di so_headers.');
            if (! $this->customerId($this->string($row, 'customer_code'))) $this->addMessage($errors, 'fulfillment_headers', $rowNo, 'customer_code tidak ditemukan.');
            if (! $this->warehouseId($this->string($row, 'warehouse_code'))) $this->addMessage($errors, 'fulfillment_headers', $rowNo, 'warehouse_code tidak ditemukan.');
            if ($fulfillmentNo !== '' && DB::table('shipments')->where('number', $fulfillmentNo)->exists()) $this->addMessage($errors, 'fulfillment_headers', $rowNo, "fulfillment_no {$fulfillmentNo} sudah ada.");
        }
        foreach ($this->sheetRows($sheets, 'fulfillment_lines') as $index => $row) {
            $rowNo = $index + 2;
            $fulfillmentNo = $this->string($row, 'fulfillment_no');
            $soLineKey = $this->string($row, 'so_no').'#'.$this->string($row, 'so_line_no');
            if (! isset($fulfillmentNos[$fulfillmentNo])) $this->addMessage($errors, 'fulfillment_lines', $rowNo, 'fulfillment_no tidak ditemukan di fulfillment_headers.');
            if (! isset($soLineKeys[$soLineKey])) $this->addMessage($errors, 'fulfillment_lines', $rowNo, 'so_no + so_line_no tidak ditemukan.');
            if (! $this->itemId($this->string($row, 'item_sku'))) $this->addMessage($errors, 'fulfillment_lines', $rowNo, 'item_sku tidak ditemukan.');
            if (! $this->uomId($this->string($row, 'uom_code'))) $this->addMessage($errors, 'fulfillment_lines', $rowNo, 'uom_code tidak ditemukan.');
            if ($this->decimal($row, 'qty_fulfilled') <= 0) $this->addMessage($errors, 'fulfillment_lines', $rowNo, 'qty_fulfilled harus > 0.');
            $key = $fulfillmentNo.'#'.$this->string($row, 'line_no');
            if (isset($fulfillmentLineKeys[$key])) $this->addMessage($errors, 'fulfillment_lines', $rowNo, "Line fulfillment {$key} duplikat.");
            $fulfillmentLineKeys[$key] = true;
        }

        $invoiceNos = [];
        foreach ($this->sheetRows($sheets, 'invoice_headers') as $index => $row) {
            $rowNo = $index + 2;
            $invoiceNo = $this->string($row, 'invoice_no');
            if ($invoiceNo === '') $this->addMessage($errors, 'invoice_headers', $rowNo, 'invoice_no wajib diisi.');
            if (isset($invoiceNos[$invoiceNo])) $this->addMessage($errors, 'invoice_headers', $rowNo, "invoice {$invoiceNo} duplikat.");
            $invoiceNos[$invoiceNo] = true;
            if (! $this->customerId($this->string($row, 'customer_code'))) $this->addMessage($errors, 'invoice_headers', $rowNo, 'customer_code tidak ditemukan.');
            if ($invoiceNo !== '' && DB::table('customer_invoices')->where('number', $invoiceNo)->exists()) $this->addMessage($errors, 'invoice_headers', $rowNo, "invoice_no {$invoiceNo} sudah ada.");
        }
        foreach ($this->sheetRows($sheets, 'invoice_lines') as $index => $row) {
            $rowNo = $index + 2;
            if (! isset($invoiceNos[$this->string($row, 'invoice_no')])) $this->addMessage($errors, 'invoice_lines', $rowNo, 'invoice_no tidak ditemukan di invoice_headers.');
            if ($this->string($row, 'fulfillment_no') !== '' && $this->string($row, 'fulfillment_line_no') !== '' && ! isset($fulfillmentLineKeys[$this->string($row, 'fulfillment_no').'#'.$this->string($row, 'fulfillment_line_no')])) $this->addMessage($errors, 'invoice_lines', $rowNo, 'fulfillment_no + fulfillment_line_no tidak ditemukan.');
            if (! $this->itemId($this->string($row, 'item_sku'))) $this->addMessage($errors, 'invoice_lines', $rowNo, 'item_sku tidak ditemukan.');
            if ($this->decimal($row, 'qty_invoiced') <= 0) $this->addMessage($errors, 'invoice_lines', $rowNo, 'qty_invoiced harus > 0.');
        }

        $collectionNos = [];
        $collectionByInvoice = [];
        foreach ($this->sheetRows($sheets, 'collection_headers') as $index => $row) {
            $rowNo = $index + 2;
            $collectionNo = $this->string($row, 'collection_no');
            if ($collectionNo === '') $this->addMessage($errors, 'collection_headers', $rowNo, 'collection_no wajib diisi.');
            if (isset($collectionNos[$collectionNo])) $this->addMessage($errors, 'collection_headers', $rowNo, "collection {$collectionNo} duplikat.");
            $collectionNos[$collectionNo] = true;
            if (! $this->customerId($this->string($row, 'customer_code'))) $this->addMessage($errors, 'collection_headers', $rowNo, 'customer_code tidak ditemukan.');
            if ($collectionNo !== '' && DB::table('customer_payments')->where('number', $collectionNo)->exists()) $this->addMessage($errors, 'collection_headers', $rowNo, "collection_no {$collectionNo} sudah ada.");
        }
        foreach ($this->sheetRows($sheets, 'collection_lines') as $index => $row) {
            $rowNo = $index + 2;
            $collectionNo = $this->string($row, 'collection_no');
            $invoiceNo = $this->string($row, 'invoice_no');
            if (! isset($collectionNos[$collectionNo])) $this->addMessage($errors, 'collection_lines', $rowNo, 'collection_no tidak ditemukan di collection_headers.');
            if (! isset($invoiceNos[$invoiceNo])) $this->addMessage($errors, 'collection_lines', $rowNo, 'invoice_no tidak ditemukan di invoice_headers.');
            $amount = $this->decimal($row, 'collection_amount') + $this->decimal($row, 'discount_taken') + $this->decimal($row, 'wht_amount') + $this->decimal($row, 'other_deduction_amount');
            if ($amount <= 0) $this->addMessage($errors, 'collection_lines', $rowNo, 'collection_amount + deduction harus > 0.');
            $collectionByInvoice[$invoiceNo] = ($collectionByInvoice[$invoiceNo] ?? 0) + $amount;
        }
        foreach ($this->sheetRows($sheets, 'invoice_headers') as $index => $row) {
            $invoiceNo = $this->string($row, 'invoice_no');
            if (($collectionByInvoice[$invoiceNo] ?? 0) > $this->decimal($row, 'grand_total') + 0.0001) $this->addMessage($errors, 'invoice_headers', $index + 2, 'Total collection lines melebihi grand total invoice.');
        }

        if (! $hasFulfillmentHeaders) $warnings[] = ['sheet' => 'fulfillment_headers', 'row' => null, 'message' => 'Tidak ada fulfillment; shipment tidak akan dibuat.'];
        if (! $hasInvoiceHeaders) $warnings[] = ['sheet' => 'invoice_headers', 'row' => null, 'message' => 'Tidak ada invoice; customer invoice tidak akan dibuat.'];
        if (! $hasCollectionHeaders) $warnings[] = ['sheet' => 'collection_headers', 'row' => null, 'message' => 'Tidak ada collection; invoice akan tetap outstanding/open.'];

        return [$errors, $warnings, $summary];
    }

    private function commitSalesOrders(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'so_headers') as $row) {
            $soNo = $this->string($row, 'so_no');
            $status = $this->normalizeSoStatus($this->string($row, 'status'));
            $soId = DB::table('sales')->insertGetId($this->filterColumns('sales', [
                'number' => $soNo,
                'warehouse_id' => $this->warehouseId($this->string($row, 'warehouse_code')),
                'customer_id' => $this->customerId($this->string($row, 'customer_code')),
                'document_date' => $this->date($row, 'so_date') ?: now()->toDateString(),
                'expected_delivery_date' => $this->date($row, 'expected_delivery_date'),
                'status' => $status,
                'subtotal' => $this->decimal($row, 'subtotal'),
                'discount_total' => $this->decimal($row, 'discount_total'),
                'tax_total' => $this->decimal($row, 'tax_total'),
                'grand_total' => $this->decimal($row, 'grand_total'),
                'notes' => $this->string($row, 'notes') ?: null,
                'posted_by' => in_array($status, ['posted', 'completed'], true) ? $userId : null,
                'posted_at' => in_array($status, ['posted', 'completed'], true) ? now() : null,
                'submitted_by' => in_array($status, ['submitted', 'approved', 'posted', 'completed'], true) ? $userId : null,
                'submitted_at' => in_array($status, ['submitted', 'approved', 'posted', 'completed'], true) ? now() : null,
                'approved_by' => in_array($status, ['approved', 'posted', 'completed'], true) ? $userId : null,
                'approved_at' => in_array($status, ['approved', 'posted', 'completed'], true) ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['so_ids'][$soNo] = $soId;
            $this->linkDocument($batch, 'sales_order', $soNo, 'sales', $soId);
        }

        foreach ($this->sheetRows($sheets, 'so_lines') as $row) {
            $soNo = $this->string($row, 'so_no');
            $lineNo = $this->string($row, 'line_no');
            $itemId = (int) $this->itemId($this->string($row, 'item_sku'));
            $uomId = (int) $this->uomId($this->string($row, 'uom_code'));
            $qty = $this->decimal($row, 'qty_ordered');
            $unitPrice = $this->decimal($row, 'unit_price');
            $discountAmount = $this->decimal($row, 'discount_amount');
            $taxAmount = $this->decimal($row, 'tax_amount');
            $lineTotal = $this->decimal($row, 'line_total') ?: ($qty * $unitPrice) - $discountAmount + $taxAmount;
            $lineId = DB::table('sales_lines')->insertGetId($this->filterColumns('sales_lines', [
                'sale_id' => $context['so_ids'][$soNo],
                'item_id' => $itemId,
                'batch_id' => $this->resolveBatchId($itemId, $this->string($row, 'batch_number'), $this->date($row, 'expired_date')),
                'facility_scheme_id' => $this->facilitySchemeId($this->string($row, 'facility_scheme_code')),
                'qty_sold' => $qty,
                'uom_id' => $uomId,
                'qty_base' => $this->resolveQtyBase($itemId, $uomId, $qty, $this->decimal($row, 'qty_base')),
                'unit_price' => $unitPrice,
                'discount_percent' => $this->decimal($row, 'discount_percent'),
                'discount_amount' => $discountAmount,
                'tax_percent' => $this->decimal($row, 'tax_percent'),
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'qty_shipped' => 0,
                'qty_invoiced' => 0,
                'notes' => $this->string($row, 'notes') ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['so_line_ids'][$soNo.'#'.$lineNo] = $lineId;
            $this->linkDocument($batch, 'sales_order_line', $soNo.'#'.$lineNo, 'sales_lines', $lineId);
        }
    }

    private function commitFulfillments(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'fulfillment_headers') as $row) {
            $fulfillmentNo = $this->string($row, 'fulfillment_no');
            $status = $this->normalizeFulfillmentStatus($this->string($row, 'status'));
            $shipmentId = DB::table('shipments')->insertGetId($this->filterColumns('shipments', [
                'number' => $fulfillmentNo,
                'sale_id' => $context['so_ids'][$this->string($row, 'so_no')] ?? null,
                'customer_id' => $this->customerId($this->string($row, 'customer_code')),
                'warehouse_id' => $this->warehouseId($this->string($row, 'warehouse_code')),
                'shipment_date' => $this->date($row, 'fulfillment_date') ?: now()->toDateString(),
                'status' => $status,
                'delivery_status' => in_array($status, ['shipped', 'delivered'], true) ? 'shipped' : 'pending',
                'driver_name' => $this->string($row, 'driver_name') ?: null,
                'vehicle_no' => $this->string($row, 'vehicle_no') ?: null,
                'courier_name' => $this->string($row, 'courier_name') ?: null,
                'tracking_number' => $this->string($row, 'tracking_number') ?: null,
                'notes' => $this->string($row, 'notes') ?: null,
                'posted_by' => in_array($status, ['shipped', 'delivered'], true) ? $userId : null,
                'posted_at' => in_array($status, ['shipped', 'delivered'], true) ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['shipment_ids'][$fulfillmentNo] = $shipmentId;
            $this->linkDocument($batch, 'fulfillment', $fulfillmentNo, 'shipments', $shipmentId);
        }

        foreach ($this->sheetRows($sheets, 'fulfillment_lines') as $row) {
            $fulfillmentNo = $this->string($row, 'fulfillment_no');
            $lineNo = $this->string($row, 'line_no');
            $soLine = DB::table('sales_lines')->where('id', $context['so_line_ids'][$this->string($row, 'so_no').'#'.$this->string($row, 'so_line_no')])->lockForUpdate()->first();
            $itemId = (int) $this->itemId($this->string($row, 'item_sku'));
            $uomId = (int) $this->uomId($this->string($row, 'uom_code'));
            $qty = $this->decimal($row, 'qty_fulfilled');
            $lineId = DB::table('shipment_lines')->insertGetId($this->filterColumns('shipment_lines', [
                'shipment_id' => $context['shipment_ids'][$fulfillmentNo],
                'sale_line_id' => $soLine->id,
                'item_id' => $itemId,
                'batch_id' => $this->resolveBatchId($itemId, $this->string($row, 'batch_number'), $this->date($row, 'expired_date')) ?: ($soLine->batch_id ?? null),
                'facility_scheme_id' => $soLine->facility_scheme_id ?? null,
                'uom_id' => $uomId,
                'qty_ordered' => $soLine->qty_sold,
                'qty_already_shipped' => $soLine->qty_shipped ?? 0,
                'qty_shipped' => $qty,
                'qty_base' => $this->resolveQtyBase($itemId, $uomId, $qty, $this->decimal($row, 'qty_base')),
                'notes' => $this->string($row, 'notes') ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            DB::table('sales_lines')->where('id', $soLine->id)->update(['qty_shipped' => (float) ($soLine->qty_shipped ?? 0) + $qty, 'updated_at' => now()]);
            $context['shipment_line_ids'][$fulfillmentNo.'#'.$lineNo] = $lineId;
            $this->linkDocument($batch, 'fulfillment_line', $fulfillmentNo.'#'.$lineNo, 'shipment_lines', $lineId);
        }
    }

    private function commitInvoices(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'invoice_headers') as $row) {
            $invoiceNo = $this->string($row, 'invoice_no');
            $grandTotal = $this->decimal($row, 'grand_total');
            $paid = $this->decimal($row, 'amount_paid');
            $balance = $this->string($row, 'balance_due') !== '' ? $this->decimal($row, 'balance_due') : max(0, $grandTotal - $paid);
            $status = $this->normalizeInvoiceStatus($this->string($row, 'status'), $paid, $balance);
            $invoiceId = DB::table('customer_invoices')->insertGetId($this->filterColumns('customer_invoices', [
                'number' => $invoiceNo,
                'customer_id' => $this->customerId($this->string($row, 'customer_code')),
                'sale_id' => $context['so_ids'][$this->string($row, 'so_no')] ?? null,
                'shipment_id' => $context['shipment_ids'][$this->string($row, 'fulfillment_no')] ?? null,
                'invoice_date' => $this->date($row, 'invoice_date') ?: now()->toDateString(),
                'due_date' => $this->date($row, 'due_date'),
                'status' => $status,
                'subtotal' => $this->decimal($row, 'subtotal'),
                'discount_total' => $this->decimal($row, 'discount_total'),
                'tax_total' => $this->decimal($row, 'tax_total'),
                'freight_amount' => $this->decimal($row, 'freight_amount'),
                'grand_total' => $grandTotal,
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'notes' => $this->string($row, 'notes') ?: null,
                'posted_by' => in_array($status, ['posted', 'partially_paid', 'paid'], true) ? $userId : null,
                'posted_at' => in_array($status, ['posted', 'partially_paid', 'paid'], true) ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['invoice_ids'][$invoiceNo] = $invoiceId;
            $this->linkDocument($batch, 'customer_invoice', $invoiceNo, 'customer_invoices', $invoiceId);
        }

        foreach ($this->sheetRows($sheets, 'invoice_lines') as $row) {
            $invoiceNo = $this->string($row, 'invoice_no');
            $lineNo = $this->string($row, 'line_no');
            $itemId = (int) $this->itemId($this->string($row, 'item_sku'));
            $qty = $this->decimal($row, 'qty_invoiced');
            $unitPrice = $this->decimal($row, 'unit_price');
            $discountAmount = $this->decimal($row, 'discount_amount');
            $taxAmount = $this->decimal($row, 'tax_amount');
            $lineTotal = $this->decimal($row, 'line_total') ?: ($qty * $unitPrice) - $discountAmount + $taxAmount;
            $shipmentLineKey = $this->string($row, 'fulfillment_no').'#'.$this->string($row, 'fulfillment_line_no');
            $saleLineKey = $this->string($row, 'so_no').'#'.$this->string($row, 'so_line_no');
            $lineId = DB::table('customer_invoice_lines')->insertGetId($this->filterColumns('customer_invoice_lines', [
                'customer_invoice_id' => $context['invoice_ids'][$invoiceNo],
                'shipment_line_id' => $context['shipment_line_ids'][$shipmentLineKey] ?? null,
                'sale_line_id' => $context['so_line_ids'][$saleLineKey] ?? null,
                'item_id' => $itemId,
                'uom_id' => $this->uomId($this->string($row, 'uom_code')),
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_percent' => $this->decimal($row, 'discount_percent'),
                'discount_amount' => $discountAmount,
                'tax_percent' => $this->decimal($row, 'tax_percent'),
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            if (isset($context['so_line_ids'][$saleLineKey])) {
                DB::table('sales_lines')->where('id', $context['so_line_ids'][$saleLineKey])->increment('qty_invoiced', $qty, ['updated_at' => now()]);
            }
            $this->linkDocument($batch, 'customer_invoice_line', $invoiceNo.'#'.$lineNo, 'customer_invoice_lines', $lineId);
        }
    }

    private function commitCollections(array $sheets, object $batch, ?int $userId, array &$context): void
    {
        foreach ($this->sheetRows($sheets, 'collection_headers') as $row) {
            $collectionNo = $this->string($row, 'collection_no');
            $gross = $this->decimal($row, 'gross_settlement_amount') ?: $this->decimal($row, 'amount');
            if ($gross <= 0) {
                $gross = collect($this->sheetRows($sheets, 'collection_lines'))->where('collection_no', $collectionNo)->sum(fn (array $line): float => $this->decimal($line, 'collection_amount'));
            }
            $status = $this->normalizeCollectionStatus($this->string($row, 'status'));
            $paymentId = DB::table('customer_payments')->insertGetId($this->filterColumns('customer_payments', [
                'number' => $collectionNo,
                'customer_id' => $this->customerId($this->string($row, 'customer_code')),
                'payment_date' => $this->date($row, 'collection_date') ?: now()->toDateString(),
                'payment_method' => $this->string($row, 'payment_method') ?: null,
                'cash_account_id' => $this->cashAccountId($this->string($row, 'cash_account_code')),
                'amount' => $this->decimal($row, 'amount') ?: $gross,
                'bank_charge' => $this->decimal($row, 'bank_charge'),
                'discount_taken' => $this->decimal($row, 'discount_taken'),
                'wht_amount' => $this->decimal($row, 'wht_amount'),
                'other_deduction_amount' => $this->decimal($row, 'other_deduction_amount'),
                'gross_settlement_amount' => $gross,
                'status' => $status,
                'notes' => $this->string($row, 'notes') ?: null,
                'posted_by' => $status === 'posted' ? $userId : null,
                'posted_at' => $status === 'posted' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $context['payment_ids'][$collectionNo] = $paymentId;
            $this->linkDocument($batch, 'customer_collection', $collectionNo, 'customer_payments', $paymentId);
        }

        foreach ($this->sheetRows($sheets, 'collection_lines') as $row) {
            $collectionNo = $this->string($row, 'collection_no');
            $invoiceNo = $this->string($row, 'invoice_no');
            $amount = $this->decimal($row, 'collection_amount');
            $discount = $this->decimal($row, 'discount_taken');
            $wht = $this->decimal($row, 'wht_amount');
            $other = $this->decimal($row, 'other_deduction_amount');
            DB::table('customer_payment_allocations')->insert($this->filterColumns('customer_payment_allocations', [
                'customer_payment_id' => $context['payment_ids'][$collectionNo],
                'customer_invoice_id' => $context['invoice_ids'][$invoiceNo],
                'amount_applied' => $amount,
                'discount_taken' => $discount,
                'wht_amount' => $wht,
                'other_deduction_amount' => $other,
                'writeoff_amount' => $this->decimal($row, 'writeoff_amount'),
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $invoice = DB::table('customer_invoices')->where('id', $context['invoice_ids'][$invoiceNo])->lockForUpdate()->first();
            $paid = (float) ($invoice->amount_paid ?? 0) + $amount;
            $balance = max(0, (float) ($invoice->grand_total ?? 0) - $paid - $discount - $wht - $other);
            DB::table('customer_invoices')->where('id', $invoice->id)->update([
                'amount_paid' => $paid,
                'balance_due' => $balance,
                'status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : ($invoice->status ?? 'posted')),
                'updated_at' => now(),
            ]);
        }
    }

    private function insertPreviewRows(int $batchId, array $sheets, array $errors, array $warnings): void
    {
        foreach ($sheets as $sheetName => $rows) {
            foreach ($rows as $index => $row) {
                DB::table('manual_sales_integration_rows')->insert([
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
    }

    private function refreshSalesOrderFulfillment(array $soIds): void
    {
        foreach ($soIds as $soId) {
            $lines = DB::table('sales_lines')->where('sale_id', $soId)->get();
            if ($lines->isEmpty()) continue;
            $allShipped = $lines->every(fn (object $line): bool => (float) ($line->qty_shipped ?? 0) >= (float) $line->qty_sold - 0.0001);
            $hasShipped = $lines->contains(fn (object $line): bool => (float) ($line->qty_shipped ?? 0) > 0);
            $allInvoiced = $lines->every(fn (object $line): bool => (float) ($line->qty_invoiced ?? 0) >= (float) $line->qty_sold - 0.0001);
            DB::table('sales')->where('id', $soId)->update($this->filterColumns('sales', [
                'status' => $allInvoiced ? 'fully_invoiced' : ($allShipped ? 'fully_shipped' : ($hasShipped ? 'partially_shipped' : (DB::table('sales')->where('id', $soId)->value('status') ?: 'approved'))),
                'updated_at' => now(),
            ]));
        }
    }

    private function templateSheets(): array
    {
        return [
            'README' => [
                ['manual_sales_integration_template', 'v1'],
                ['Catatan', 'Master customer/item/uom/warehouse harus sudah ada. Flow dapat diproses parsial dari kiri ke kanan: SO saja, SO + Fulfillment, SO + Fulfillment + Invoice, atau lengkap sampai Collection. Untuk invoice tanpa collection, isi amount_paid = 0 dan balance_due = grand_total; kosongkan sheet collection. Tidak membuat jurnal akunting.'],
            ],
            'so_headers' => [
                ['so_no', 'so_date', 'expected_delivery_date', 'customer_code', 'warehouse_code', 'status', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'notes'],
                ['SO-LAMA-001', '2026-01-05', '2026-01-10', 'CUST-001', 'WH-UTAMA', 'approved', '1000000', '0', '110000', '1110000', 'Contoh SO historical'],
            ],
            'so_lines' => [
                ['so_no', 'line_no', 'item_sku', 'batch_number', 'expired_date', 'uom_code', 'qty_ordered', 'qty_base', 'unit_price', 'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount', 'line_total', 'facility_scheme_code', 'notes'],
                ['SO-LAMA-001', '1', 'SKU-001', 'BATCH-001', '2027-01-31', 'PCS', '10', '', '100000', '0', '0', '11', '110000', '1110000', '', 'Contoh line SO'],
            ],
            'fulfillment_headers' => [
                ['fulfillment_no', 'fulfillment_date', 'so_no', 'customer_code', 'warehouse_code', 'status', 'driver_name', 'vehicle_no', 'courier_name', 'tracking_number', 'notes'],
                ['SHP-LAMA-001', '2026-01-08', 'SO-LAMA-001', 'CUST-001', 'WH-UTAMA', 'shipped', '', '', 'Kurir ABC', 'TRK001', 'Contoh fulfillment shipped/posted'],
            ],
            'fulfillment_lines' => [
                ['fulfillment_no', 'line_no', 'so_no', 'so_line_no', 'item_sku', 'batch_number', 'expired_date', 'uom_code', 'qty_fulfilled', 'qty_base', 'notes'],
                ['SHP-LAMA-001', '1', 'SO-LAMA-001', '1', 'SKU-001', 'BATCH-001', '2027-01-31', 'PCS', '10', '', 'Contoh fulfillment line'],
            ],
            'invoice_headers' => [
                ['invoice_no', 'invoice_date', 'due_date', 'customer_code', 'so_no', 'fulfillment_no', 'status', 'subtotal', 'discount_total', 'tax_total', 'freight_amount', 'grand_total', 'amount_paid', 'balance_due', 'notes'],
                ['CI-LAMA-001', '2026-01-09', '2026-02-08', 'CUST-001', 'SO-LAMA-001', 'SHP-LAMA-001', 'posted', '1000000', '0', '110000', '0', '1110000', '0', '1110000', 'Contoh invoice outstanding tanpa collection'],
            ],
            'invoice_lines' => [
                ['invoice_no', 'line_no', 'so_no', 'so_line_no', 'fulfillment_no', 'fulfillment_line_no', 'item_sku', 'description', 'uom_code', 'qty_invoiced', 'unit_price', 'discount_percent', 'discount_amount', 'tax_percent', 'tax_amount', 'line_total'],
                ['CI-LAMA-001', '1', 'SO-LAMA-001', '1', 'SHP-LAMA-001', '1', 'SKU-001', 'Contoh item', 'PCS', '10', '100000', '0', '0', '11', '110000', '1110000'],
            ],
            'collection_headers' => [
                ['collection_no', 'collection_date', 'customer_code', 'payment_method', 'cash_account_code', 'status', 'amount', 'bank_charge', 'discount_taken', 'wht_amount', 'other_deduction_amount', 'gross_settlement_amount', 'notes'],
            ],
            'collection_lines' => [
                ['collection_no', 'line_no', 'invoice_no', 'collection_amount', 'discount_taken', 'wht_amount', 'other_deduction_amount', 'writeoff_amount', 'notes'],
            ],
        ];
    }

    private function linkDocument(object $batch, string $type, string $no, string $table, int $id): void
    {
        DB::table('manual_sales_integration_document_links')->insert([
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

    private function parseWorkbook(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return [];
        $sharedStrings = $this->readSharedStrings($zip);
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (! $workbookXml || ! $relsXml) return [];
        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        $relationshipTargets = [];
        foreach ($rels?->Relationship ?? [] as $rel) $relationshipTargets[(string) $rel['Id']] = (string) $rel['Target'];
        $sheets = [];
        if ($workbook !== false && isset($workbook->sheets->sheet)) {
            foreach ($workbook->sheets->sheet as $sheet) {
                $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                $rid = (string) ($attrs['id'] ?? '');
                $target = $relationshipTargets[$rid] ?? '';
                if ($target === '') continue;
                $sheetXml = $zip->getFromName('xl/'.ltrim($target, '/')) ?: $zip->getFromName('xl/worksheets/'.basename($target));
                if (! $sheetXml) continue;
                $sheets[(string) $sheet['name']] = $this->parseSheetXml($sheetXml, $sharedStrings);
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
                preg_match('/([A-Z]+)/', (string) ($cell['r'] ?? 'A1'), $match);
                $index = $this->xlsxColumnToIndex($match[1] ?? 'A');
                $type = (string) ($cell['t'] ?? '');
                $line[$index] = trim($type === 's' ? ($sharedStrings[(int) ($cell->v ?? 0)] ?? '') : ($type === 'inlineStr' ? (string) ($cell->is->t ?? '') : (isset($cell->v) ? (string) $cell->v : '')));
            }
            if ($line !== []) {
                ksort($line);
                $table[] = $line;
            }
        }
        if (count($table) < 1) return [];
        $header = array_map(fn ($v): string => trim((string) $v), $table[0]);
        $rows = [];
        foreach (array_slice($table, 1) as $line) $rows[] = collect($header)->mapWithKeys(fn ($key, $index): array => [$key => trim((string) ($line[$index] ?? ''))])->all();

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
                if (isset($si->t)) $strings[] = (string) $si->t;
                else {
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

    private function sheetRows(array $sheets, string $sheet): array
    {
        return collect($sheets[$sheet] ?? [])->filter(fn (array $row): bool => ! $this->isRowEmpty($row))->values()->all();
    }

    private function requireHeaders(array $sheets, string $sheet, array $headers, array &$errors): void
    {
        if (! array_key_exists($sheet, $sheets)) {
            $this->addMessage($errors, $sheet, null, "Sheet {$sheet} tidak ditemukan.");
            return;
        }
        $first = $sheets[$sheet][0] ?? [];
        foreach ($headers as $header) if (! array_key_exists($header, $first)) $this->addMessage($errors, $sheet, 1, "Header {$header} wajib ada.");
    }

    private function addMessage(array &$messages, string $sheet, ?int $row, string $message): void { $messages[] = ['sheet' => $sheet, 'row' => $row, 'message' => $message]; }
    private function rowHasError(array $errors, string $sheet, int $row): bool { return collect($errors)->contains(fn (array $error): bool => $error['sheet'] === $sheet && (int) ($error['row'] ?? 0) === $row); }
    private function messagesForRow(array $errors, array $warnings, string $sheet, int $row): array { return collect($errors)->concat($warnings)->filter(fn (array $message): bool => $message['sheet'] === $sheet && (int) ($message['row'] ?? 0) === $row)->values()->all(); }
    private function string(array $row, string $key): string { return trim((string) ($row[$key] ?? '')); }
    private function decimal(array $row, string $key): float { $value = str_replace([',', ' '], ['', ''], $this->string($row, $key)); return is_numeric($value) ? (float) $value : 0.0; }
    private function date(array $row, string $key): ?string { $value = $this->string($row, $key); if ($value === '') return null; if (is_numeric($value)) return Carbon::create(1899, 12, 30)->addDays((int) $value)->toDateString(); try { return Carbon::parse($value)->toDateString(); } catch (\Throwable) { return null; } }
    private function customerId(string $code): ?int { return $code === '' ? null : DB::table('customers')->where('customer_code', $code)->value('id'); }
    private function warehouseId(string $code): ?int { return $code === '' ? null : DB::table('warehouses')->where('code', $code)->value('id'); }
    private function itemId(string $sku): ?int { return $sku === '' ? null : DB::table('items')->where('sku', $sku)->value('id'); }
    private function uomId(string $code): ?int { return $code === '' ? null : DB::table('uoms')->where('code', $code)->value('id'); }
    private function cashAccountId(string $code): ?int { return $code === '' || ! Schema::hasTable('cash_accounts') ? null : DB::table('cash_accounts')->where('code', $code)->value('id'); }
    private function facilitySchemeId(string $code): ?int { return $code === '' || ! Schema::hasTable('facility_schemes') ? null : DB::table('facility_schemes')->where('code', $code)->orWhere('name', $code)->value('id'); }
    private function documentLinkExists(string $sourceSystem, string $sourceBranchCode, string $type, string $no): bool { return DB::table('manual_sales_integration_document_links')->where('source_system', $sourceSystem)->where('source_branch_code', $sourceBranchCode)->where('document_type', $type)->where('document_no', $no)->exists(); }
    private function normalizeSoStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'completed') return 'closed';

        return in_array($status, ['draft', 'submitted', 'approved', 'posted', 'cancelled', 'partially_shipped', 'fully_shipped', 'fully_invoiced', 'closed'], true) ? $status : 'approved';
    }

    private function normalizeFulfillmentStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'posted') return 'shipped';

        return in_array($status, ['draft', 'picked', 'packed', 'shipped', 'delivered', 'cancelled'], true) ? $status : 'shipped';
    }
    private function normalizeInvoiceStatus(string $status, float $paid, float $balance): string
    {
        $status = strtolower(trim($status));
        if ($status === 'paid' && $balance > 0.0001) return $paid > 0 ? 'partially_paid' : 'posted';
        if ($status === 'partially_paid' && $paid <= 0.0001) return 'posted';
        if (in_array($status, ['draft', 'posted', 'partially_paid', 'paid', 'overdue', 'cancelled'], true)) return $status;

        return $balance <= 0 ? 'paid' : ($paid > 0 ? 'partially_paid' : 'posted');
    }
    private function normalizeCollectionStatus(string $status): string { $status = strtolower(trim($status)); return in_array($status, ['draft', 'posted', 'cancelled'], true) ? $status : 'posted'; }
    private function filterColumns(string $table, array $payload): array { $validColumns = array_flip(Schema::getColumnListing($table)); return array_filter($payload, fn (string $column): bool => isset($validColumns[$column]), ARRAY_FILTER_USE_KEY); }
    private function isRowEmpty(array $row): bool { foreach ($row as $value) if (trim((string) $value) !== '') return false; return true; }
    private function xlsxColumnToIndex(string $column): int { $index = 0; foreach (str_split($column) as $char) $index = $index * 26 + (ord($char) - 64); return $index - 1; }
    private function indexToXlsxColumn(int $index): string { $column = ''; $index++; while ($index > 0) { $remainder = ($index - 1) % 26; $column = chr(65 + $remainder).$column; $index = intdiv($index - 1, 26); } return $column; }
    private function resolveQtyBase(int $itemId, int $uomId, float $qtyInput, float $qtyBase): float { if ($qtyBase !== 0.0) return $qtyBase; return $this->uomConversionService->toBase($itemId, $uomId, $qtyInput); }
    private function resolveBatchId(int $itemId, string $batchNumber, ?string $expiredDate): ?int { $batchNumber = trim($batchNumber); if ($batchNumber === '') return null; $query = DB::table('item_batches')->where('item_id', $itemId)->where('batch_no', $batchNumber); if ($expiredDate) $query->where('expired_date', $expiredDate); $id = $query->value('id'); if ($id) return (int) $id; return DB::table('item_batches')->insertGetId(['item_id' => $itemId, 'batch_no' => $batchNumber, 'expired_date' => $expiredDate, 'created_at' => now(), 'updated_at' => now()]); }
}
