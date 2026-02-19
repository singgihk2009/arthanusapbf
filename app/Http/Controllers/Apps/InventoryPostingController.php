<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\OpeningBalanceImportRequest;
use App\Http\Requests\Inventory\OpeningBalanceRequest;
use App\Models\Inventory\Item;
use App\Services\Inventory\BatchAllocationService;
use App\Services\Inventory\StockService;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Response;
use ZipArchive;

class InventoryPostingController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly UomConversionService $uomConversionService,
        private readonly BatchAllocationService $batchAllocationService,
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory-posting-grn', only: ['postGoodsReceipt']),
            new Middleware('permission:inventory-posting-transfer', only: ['postTransfer']),
            new Middleware('permission:inventory-posting-sale', only: ['postSale']),
            new Middleware('permission:inventory-posting-usage', only: ['postInternalUsage']),
            new Middleware('permission:inventory-posting-adjustment', only: ['postStockAdjustment']),
            new Middleware('permission:inventory-posting-opening-balance', only: ['openingBalancePage', 'postOpeningBalance', 'importOpeningBalance', 'downloadOpeningBalanceTemplateCsv', 'downloadOpeningBalanceTemplateExcel']),
        ];
    }

    public function openingBalancePage(): Response
    {
        return inertia('Apps/Inventory/OpeningBalance/Index', [
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('code')->get(),
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('sku')->limit(500)->get(),
            'uoms' => DB::table('uoms')->select('id', 'code', 'name')->orderBy('code')->get(),
        ]);
    }

    public function postOpeningBalance(OpeningBalanceRequest $request): JsonResponse
    {
        $ledger = $this->createOpeningBalanceMutation($request->validated(), $request->user()?->id);

        return response()->json([
            'message' => 'Opening balance posted',
            'id' => $ledger->id,
        ]);
    }

    public function importOpeningBalance(OpeningBalanceImportRequest $request): JsonResponse
    {
        $rows = $this->parseImportRows($request->file('file'));
        $created = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $request, &$created, &$errors) {
            foreach ($rows as $index => $row) {
                if ($this->isRowEmpty($row)) {
                    continue;
                }

                try {
                    $validated = validator($row, [
                        'warehouse_code' => ['required', 'string'],
                        'item_sku' => ['required', 'string'],
                        'qty' => ['required', 'numeric', 'gt:0'],
                        'uom_code' => ['required', 'string'],
                        'unit_cost' => ['required', 'numeric', 'min:0'],
                        'batch_no' => ['nullable', 'string'],
                        'trx_datetime' => ['nullable', 'date'],
                    ])->validate();

                    $warehouseId = (int) DB::table('warehouses')->where('code', $validated['warehouse_code'])->value('id');
                    $itemId = (int) DB::table('items')->where('sku', $validated['item_sku'])->value('id');
                    $uomId = (int) DB::table('uoms')->where('code', $validated['uom_code'])->value('id');

                    if (! $warehouseId || ! $itemId || ! $uomId) {
                        throw new \RuntimeException('warehouse_code / item_sku / uom_code tidak ditemukan');
                    }

                    $batchId = null;
                    if (! empty($validated['batch_no'])) {
                        $batchId = DB::table('item_batches')
                            ->where('item_id', $itemId)
                            ->where('batch_no', $validated['batch_no'])
                            ->value('id');

                        if (! $batchId) {
                            $batchId = DB::table('item_batches')->insertGetId([
                                'item_id' => $itemId,
                                'batch_no' => $validated['batch_no'],
                                'expired_date' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $this->createOpeningBalanceMutation([
                        'warehouse_id' => $warehouseId,
                        'item_id' => $itemId,
                        'batch_id' => $batchId,
                        'qty' => (float) $validated['qty'],
                        'uom_id' => $uomId,
                        'unit_cost' => (float) $validated['unit_cost'],
                        'trx_datetime' => $validated['trx_datetime'] ?? null,
                    ], $request->user()?->id);

                    $created++;
                } catch (\Throwable $exception) {
                    $errors[] = [
                        'row' => $index + 2,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'message' => 'Import selesai',
            'created' => $created,
            'errors' => $errors,
        ]);
    }

    public function downloadOpeningBalanceTemplateCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="opening-balance-template.csv"',
        ];

        $columns = ['warehouse_code', 'item_sku', 'qty', 'uom_code', 'unit_cost', 'batch_no', 'trx_datetime'];
        $sample = ['WH-01', 'SKU-001', '10', 'PCS', '15000', 'BATCH-001', now()->format('Y-m-d H:i:s')];

        $callback = static function () use ($columns, $sample) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $columns);
            fputcsv($stream, $sample);
            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadOpeningBalanceTemplateExcel()
    {
        $tempPath = storage_path('app/opening-balance-template.xlsx');
        $this->buildTemplateXlsx($tempPath, [
            ['warehouse_code', 'item_sku', 'qty', 'uom_code', 'unit_cost', 'batch_no', 'trx_datetime'],
            ['WH-01', 'SKU-001', '10', 'PCS', '15000', 'BATCH-001', now()->format('Y-m-d H:i:s')],
        ]);

        return response()->download($tempPath, 'opening-balance-template.xlsx')->deleteFileAfterSend(true);
    }

    public function postGoodsReceipt(Request $request, int $goodsReceipt): JsonResponse
    {
        $header = DB::table('goods_receipts')->where('id', $goodsReceipt)->first();
        abort_unless($header, 404, 'Goods receipt not found');
        abort_if($header->status === 'POSTED', 422, 'Goods receipt already posted');

        $lines = DB::table('goods_receipt_lines')->where('goods_receipt_id', $goodsReceipt)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_received, (float) $line->qty_base);

            $this->stockService->postMutation([
                'trx_type' => 'PO_RECEIVE',
                'trx_id' => $goodsReceipt,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'qty_base' => $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_received,
                'unit_cost' => $line->unit_price,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('goods_receipts')->where('id', $goodsReceipt)->update([
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Goods receipt posted', 'id' => $goodsReceipt]);
    }

    public function postTransfer(Request $request, int $transferId): JsonResponse
    {
        $header = DB::table('warehouse_transfers')->where('id', $transferId)->first();
        abort_unless($header, 404, 'Transfer not found');
        abort_if($header->status === 'RECEIVED', 422, 'Transfer already received/posted');

        $lines = DB::table('warehouse_transfer_lines')->where('warehouse_transfer_id', $transferId)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_requested, (float) $line->qty_base);

            $this->stockService->postMutation([
                'trx_type' => 'TRANSFER_OUT',
                'trx_id' => $transferId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->from_warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'qty_base' => -1 * $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_requested,
                'created_by' => $request->user()?->id,
            ]);

            $this->stockService->postMutation([
                'trx_type' => 'TRANSFER_IN',
                'trx_id' => $transferId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->to_warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'qty_base' => $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_requested,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('warehouse_transfers')->where('id', $transferId)->update([
            'status' => 'RECEIVED',
            'received_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Transfer posted', 'id' => $transferId]);
    }

    public function postSale(Request $request, int $saleId): JsonResponse
    {
        $header = DB::table('sales')->where('id', $saleId)->first();
        abort_unless($header, 404, 'Sale not found');
        abort_if($header->status === 'POSTED', 422, 'Sale already posted');

        $lines = DB::table('sales_lines')->where('sale_id', $saleId)->get();

        foreach ($lines as $line) {
            $item = Item::query()->findOrFail($line->item_id);
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_sold, (float) $line->qty_base);

            if ($item->track_expired) {
                $allocations = $this->batchAllocationService->allocateFefo((int) $header->warehouse_id, (int) $line->item_id, $qtyBase);

                foreach ($allocations as $allocation) {
                    $this->stockService->postMutation([
                        'trx_type' => 'SALE_OUT',
                        'trx_id' => $saleId,
                        'trx_line_id' => $line->id,
                        'warehouse_id' => $header->warehouse_id,
                        'item_id' => $line->item_id,
                        'batch_id' => $allocation['batch_id'],
                        'qty_base' => -1 * $allocation['qty_base'],
                        'uom_id' => $line->uom_id,
                        'qty_input' => $line->qty_sold,
                        'created_by' => $request->user()?->id,
                    ]);
                }

                continue;
            }

            $this->stockService->postMutation([
                'trx_type' => 'SALE_OUT',
                'trx_id' => $saleId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->warehouse_id,
                'item_id' => $line->item_id,
                'qty_base' => -1 * $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_sold,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('sales')->where('id', $saleId)->update([
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Sale posted', 'id' => $saleId]);
    }

    public function postInternalUsage(Request $request, int $usageId): JsonResponse
    {
        $header = DB::table('internal_usages')->where('id', $usageId)->first();
        abort_unless($header, 404, 'Internal usage not found');
        abort_if($header->status === 'POSTED', 422, 'Internal usage already posted');

        $lines = DB::table('internal_usage_lines')->where('internal_usage_id', $usageId)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_used, (float) $line->qty_base);

            $this->stockService->postMutation([
                'trx_type' => 'USAGE_OUT',
                'trx_id' => $usageId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->warehouse_id,
                'item_id' => $line->item_id,
                'qty_base' => -1 * $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_used,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('internal_usages')->where('id', $usageId)->update([
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Internal usage posted', 'id' => $usageId]);
    }

    public function postStockAdjustment(Request $request, int $adjustmentId): JsonResponse
    {
        $header = DB::table('stock_adjustments')->where('id', $adjustmentId)->first();
        abort_unless($header, 404, 'Stock adjustment not found');
        abort_if($header->status === 'POSTED', 422, 'Stock adjustment already posted');

        $lines = DB::table('stock_adjustment_lines')->where('stock_adjustment_id', $adjustmentId)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_adjusted, (float) $line->qty_base);

            $this->stockService->postMutation([
                'trx_type' => 'ADJ',
                'trx_id' => $adjustmentId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $line->batch_id,
                'qty_base' => $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_adjusted,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('stock_adjustments')->where('id', $adjustmentId)->update([
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $request->user()?->id,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Stock adjustment posted', 'id' => $adjustmentId]);
    }

    private function createOpeningBalanceMutation(array $validated, ?int $userId)
    {
        $qtyBase = $this->resolveQtyBase((int) $validated['item_id'], (int) $validated['uom_id'], (float) $validated['qty'], 0);

        return $this->stockService->postMutation([
            'trx_type' => 'OPENING_BALANCE',
            'trx_id' => $this->generateOpeningBalanceTrxId(),
            'warehouse_id' => $validated['warehouse_id'],
            'item_id' => $validated['item_id'],
            'batch_id' => $validated['batch_id'] ?? null,
            'qty_base' => $qtyBase,
            'uom_id' => $validated['uom_id'],
            'qty_input' => $validated['qty'],
            'unit_cost' => $validated['unit_cost'],
            'trx_datetime' => $validated['trx_datetime'] ?? now(),
            'created_by' => $userId,
        ]);
    }

    private function parseImportRows(UploadedFile $file): Collection
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match ($ext) {
            'xlsx' => $this->parseXlsxRows($file->getRealPath()),
            default => $this->parseCsvRows($file->getRealPath()),
        };
    }

    private function parseCsvRows(string $path): Collection
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return collect();
        }

        $header = null;
        while (($data = fgetcsv($handle)) !== false) {
            if (! $header) {
                $header = array_map(fn ($item) => trim((string) $item), $data);
                continue;
            }

            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($data) {
                return [$key => trim((string) ($data[$index] ?? ''))];
            })->all();
        }

        fclose($handle);

        return collect($rows);
    }

    private function parseXlsxRows(string $path): Collection
    {
        $zip = new ZipArchive();
        $opened = $zip->open($path);

        if ($opened !== true) {
            return collect();
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared !== false && isset($shared->si)) {
                foreach ($shared->si as $si) {
                    $sharedStrings[] = isset($si->t) ? (string) $si->t : '';
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (! $sheetXml) {
            return collect();
        }

        $xml = simplexml_load_string($sheetXml);
        if ($xml === false || ! isset($xml->sheetData->row)) {
            return collect();
        }

        $table = [];
        foreach ($xml->sheetData->row as $row) {
            $line = [];
            foreach ($row->c as $cell) {
                $type = (string) ($cell['t'] ?? '');
                $value = '';

                if ($type === 's') {
                    $idx = (int) ($cell->v ?? 0);
                    $value = $sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                    $value = (string) $cell->is->t;
                } else {
                    $value = isset($cell->v) ? (string) $cell->v : '';
                }

                $line[] = trim($value);
            }
            $table[] = $line;
        }

        if (count($table) < 2) {
            return collect();
        }

        $header = $table[0];
        $rows = [];

        foreach (array_slice($table, 1) as $line) {
            $rows[] = collect($header)->mapWithKeys(function ($key, $index) use ($line) {
                return [$key => trim((string) ($line[$index] ?? ''))];
            })->all();
        }

        return collect($rows);
    }

    private function buildTemplateXlsx(string $path, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cellXml = '';
            foreach ($row as $colIndex => $value) {
                $column = chr(65 + $colIndex);
                $escaped = htmlspecialchars((string) $value, ENT_XML1);
                $cellXml .= "<c r=\"{$column}".($rowIndex + 1)."\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
            $sheetRows .= "<row r=\"".($rowIndex + 1)."\">{$cellXml}</row>";
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();
    }

    private function generateOpeningBalanceTrxId(): int
    {
        return (int) now()->format('YmdHisu');
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function resolveQtyBase(int $itemId, int $uomId, float $qtyInput, float $qtyBase): float
    {
        if ($qtyBase > 0 || $qtyBase < 0) {
            return $qtyBase;
        }

        return $this->uomConversionService->toBase($itemId, $uomId, $qtyInput);
    }
}
