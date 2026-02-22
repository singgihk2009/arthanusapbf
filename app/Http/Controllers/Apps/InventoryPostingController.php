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
use Illuminate\Support\Facades\Schema;
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
            new Middleware('permission:inventory-posting-grn', only: ['postGoodsReceipt', 'postReceivingEntry']),
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


    public function postReceivingEntry(Request $request, int $receivingEntry): JsonResponse
    {
        $header = DB::table('receiving_entries')->where('id', $receivingEntry)->first();
        abort_unless($header, 404, 'Receiving entry not found');
        abort_if(($header->status ?? null) === 'POSTED', 422, 'Receiving entry already posted');

        $lineForeignKey = $this->resolveColumn('receiving_entry_lines', ['receiving_entry_id', 'receiving_id', 'entry_id', 'header_id']) ?? 'receiving_entry_id';
        $batchColumn = $this->resolveColumn('receiving_entry_lines', ['batch_number', 'batch_no', 'no_batch']) ?? 'batch_number';
        $warehouseId = $this->resolveWarehouseId($header);

        abort_if(! $warehouseId, 422, 'Warehouse receiving entry tidak valid.');

        $lines = DB::table('receiving_entry_lines')->where($lineForeignKey, $receivingEntry)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty, 0);
            $batchId = null;
            $batchNumber = $line->{$batchColumn} ?? null;

            if (! empty($batchNumber)) {
                $batchId = DB::table('item_batches')
                    ->where('item_id', $line->item_id)
                    ->where('batch_no', $batchNumber)
                    ->where('expired_date', $line->expired_date)
                    ->value('id');

                if (! $batchId) {
                    $batchId = DB::table('item_batches')->insertGetId([
                        'item_id' => $line->item_id,
                        'batch_no' => $batchNumber,
                        'expired_date' => $line->expired_date,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->stockService->postMutation([
                'trx_type' => 'RCV_IN',
                'trx_id' => $receivingEntry,
                'trx_line_id' => $line->id,
                'warehouse_id' => $warehouseId,
                'item_id' => $line->item_id,
                'batch_id' => $batchId,
                'qty_base' => $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty,
                'unit_cost' => $line->price,
                'created_by' => $request->user()?->id,
            ]);
        }

        DB::table('receiving_entries')->where('id', $receivingEntry)->update($this->filterColumns('receiving_entries', [
            'status' => 'POSTED',
            'posted_at' => now(),
            'posted_by' => $request->user()?->id,
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Receiving entry posted', 'id' => $receivingEntry]);
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

        $this->createIntegrationSnapshotForInternalUsage($usageId, $request->user()?->id);

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

        $this->createIntegrationSnapshotForStockAdjustment($adjustmentId, $request->user()?->id);

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

    private function createIntegrationSnapshotForInternalUsage(int $usageId, ?int $userId): void
    {
        $header = DB::table('internal_usages')->where('id', $usageId)->first();
        if (! $header) {
            return;
        }

        $lines = DB::table('internal_usage_lines as l')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->where('l.internal_usage_id', $usageId)
            ->select('l.id', 'l.item_id', 'l.qty_used', 'l.uom_id', 'l.qty_base', 'l.notes', 'i.track_expired')
            ->get();

        $this->persistIntegrationTransaction('internal_usages', $usageId, (string) $header->number, 'USAGE', (string) $header->document_date, (int) $header->warehouse_id, $lines, $userId);
    }

    private function createIntegrationSnapshotForStockAdjustment(int $adjustmentId, ?int $userId): void
    {
        $header = DB::table('stock_adjustments')->where('id', $adjustmentId)->first();
        if (! $header) {
            return;
        }

        $lines = DB::table('stock_adjustment_lines as l')
            ->join('items as i', 'i.id', '=', 'l.item_id')
            ->leftJoin('item_batches as b', 'b.id', '=', 'l.batch_id')
            ->where('l.stock_adjustment_id', $adjustmentId)
            ->select('l.id', 'l.item_id', 'l.batch_id', 'l.qty_adjusted as qty_used', 'l.uom_id', 'l.qty_base', 'l.notes', 'i.track_expired', 'b.batch_no', 'b.expired_date')
            ->get();

        $this->persistIntegrationTransaction('stock_adjustments', $adjustmentId, (string) $header->number, 'ADJUSTMENT', (string) $header->document_date, (int) $header->warehouse_id, $lines, $userId);
    }

    private function persistIntegrationTransaction(string $sourceTable, int $sourceId, string $trxNo, string $trxType, string $trxDate, int $warehouseId, Collection $lines, ?int $userId): void
    {
        $lockDate = DB::table('inventory_period_locks')->where('company_id', 1)->value('lock_date');
        if ($lockDate && $trxDate <= $lockDate) {
            abort(422, 'Tanggal transaksi sudah dikunci periode inventory.');
        }

        $totalQty = 0;
        $totalAmount = 0;
        $methods = [];
        $itemsPayload = [];

        foreach ($lines as $line) {
            $qty = abs((float) $line->qty_base);
            if ($qty <= 0) {
                continue;
            }

            $valuation = ((bool) $line->track_expired) ? 'BATCH' : 'AVG';
            $unitCost = $valuation === 'BATCH'
                ? $this->resolveBatchCost($warehouseId, (int) $line->item_id, $line->batch_id ? (int) $line->batch_id : null)
                : $this->resolveAverageCost($warehouseId, (int) $line->item_id);

            $amount = round($qty * $unitCost, 6);
            $totalQty += $qty;
            $totalAmount += $amount;
            $methods[$valuation] = true;

            $batch = null;
            if ($line->batch_id ?? null) {
                $batch = DB::table('item_batches')->where('id', $line->batch_id)->first();
            }

            $itemsPayload[] = [
                'product_id' => (int) $line->item_id,
                'warehouse_id' => $warehouseId,
                'uom_id' => (int) $line->uom_id,
                'qty' => $qty,
                'batch_id' => $line->batch_id ?? null,
                'batch_no' => $batch?->batch_no,
                'expired_date' => $batch?->expired_date,
                'valuation_method' => $valuation,
                'unit_cost_snapshot' => $unitCost,
                'amount_snapshot' => $amount,
                'cost_source' => $valuation === 'BATCH' ? 'BATCH_LAYER' : 'AVG_RATE',
                'note' => $line->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $valuationMethod = count($methods) > 1 ? 'MIXED' : (array_key_first($methods) ?? 'AVG');

        DB::transaction(function () use ($sourceTable, $sourceId, $trxNo, $trxType, $trxDate, $totalQty, $totalAmount, $valuationMethod, $itemsPayload, $userId, $warehouseId): void {
            DB::table('inv_transactions')->updateOrInsert(
                ['source_table' => $sourceTable, 'source_id' => $sourceId],
                [
                    'trx_no' => $trxNo,
                    'trx_type' => $trxType,
                    'trx_date' => $trxDate,
                    'status' => 'final',
                    'gl_status' => 'pending',
                    'valuation_method' => $valuationMethod,
                    'total_qty' => $totalQty,
                    'total_amount' => $totalAmount,
                    'posted_at' => now(),
                    'posted_by' => $userId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $transaction = DB::table('inv_transactions')->where('source_table', $sourceTable)->where('source_id', $sourceId)->first();
            if (! $transaction) {
                return;
            }

            DB::table('inv_transaction_items')->where('inv_transaction_id', $transaction->id)->delete();
            foreach ($itemsPayload as $row) {
                $row['inv_transaction_id'] = $transaction->id;
                DB::table('inv_transaction_items')->insert($row);
                $this->syncCostBalances((int) $row['product_id'], $warehouseId, (string) $row['valuation_method'], (float) $row['qty'], (float) $row['unit_cost_snapshot'], $row['batch_no'], $row['expired_date']);
            }

            $payload = [
                'idempotency_key' => "INV:TX:{$transaction->id}:v{$transaction->version}",
                'source_app' => 'inventory',
                'source_type' => 'inv_transaction',
                'source_id' => $transaction->id,
                'trx_no' => $transaction->trx_no,
                'trx_type' => $transaction->trx_type,
                'trx_date' => $transaction->trx_date,
                'warehouse_id' => $warehouseId,
                'posted_at' => $transaction->posted_at,
                'posted_by' => $transaction->posted_by,
                'totals' => ['total_qty' => (float) $transaction->total_qty, 'total_amount' => (float) $transaction->total_amount],
                'items' => DB::table('inv_transaction_items')->where('inv_transaction_id', $transaction->id)->get(['product_id', 'qty', 'uom_id', 'valuation_method', 'unit_cost_snapshot as unit_cost', 'amount_snapshot as amount', 'batch_id', 'batch_no', 'expired_date']),
            ];

            $encoded = json_encode($payload);
            $hash = hash('sha256', (string) $encoded);
            DB::table('inv_transactions')->where('id', $transaction->id)->update(['source_hash' => $hash, 'updated_at' => now()]);

            DB::table('integration_outbox')->updateOrInsert(
                ['idempotency_key' => $payload['idempotency_key']],
                [
                    'event_type' => 'INV_TX_FINAL',
                    'aggregate_type' => 'inv_transaction',
                    'aggregate_id' => $transaction->id,
                    'payload_json' => $encoded,
                    'payload_hash' => $hash,
                    'status' => 'ready',
                    'available_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }

    private function resolveAverageCost(int $warehouseId, int $itemId): float
    {
        $balance = DB::table('inv_balances')->where('company_id', 1)->where('warehouse_id', $warehouseId)->where('product_id', $itemId)->first();
        if ($balance && (float) $balance->avg_cost > 0) {
            return (float) $balance->avg_cost;
        }

        $fallback = DB::table('stock_ledgers')->where('warehouse_id', $warehouseId)->where('item_id', $itemId)->whereNotNull('unit_cost')->orderByDesc('id')->value('unit_cost');

        return (float) ($fallback ?? 0);
    }

    private function resolveBatchCost(int $warehouseId, int $itemId, ?int $batchId): float
    {
        if (! $batchId) {
            return $this->resolveAverageCost($warehouseId, $itemId);
        }

        $batchNo = DB::table('item_batches')->where('id', $batchId)->value('batch_no');
        $invBatch = DB::table('inv_batches')->where('company_id', 1)->where('warehouse_id', $warehouseId)->where('product_id', $itemId)->where('batch_no', $batchNo)->first();
        if ($invBatch && (float) $invBatch->unit_cost > 0) {
            return (float) $invBatch->unit_cost;
        }

        $fallback = DB::table('stock_ledgers')->where('warehouse_id', $warehouseId)->where('item_id', $itemId)->where('batch_id', $batchId)->whereNotNull('unit_cost')->orderByDesc('id')->value('unit_cost');

        return (float) ($fallback ?? 0);
    }

    private function syncCostBalances(int $itemId, int $warehouseId, string $valuationMethod, float $qty, float $unitCost, ?string $batchNo, ?string $expiredDate): void
    {
        $balance = DB::table('inv_balances')->where('company_id', 1)->where('warehouse_id', $warehouseId)->where('product_id', $itemId)->first();
        $onHand = (float) ($balance->on_hand_qty ?? 0);
        $stockValue = (float) ($balance->stock_value ?? 0);

        $newOnHand = max(0, $onHand - $qty);
        $newStockValue = max(0, $stockValue - ($qty * $unitCost));
        $avg = $newOnHand > 0 ? ($newStockValue / $newOnHand) : 0;

        DB::table('inv_balances')->updateOrInsert(
            ['company_id' => 1, 'warehouse_id' => $warehouseId, 'product_id' => $itemId],
            ['on_hand_qty' => $newOnHand, 'avg_cost' => $avg, 'stock_value' => $newStockValue, 'updated_at' => now(), 'created_at' => now()]
        );

        if ($valuationMethod !== 'BATCH' || ! $batchNo) {
            return;
        }

        $batch = DB::table('inv_batches')->where('company_id', 1)->where('warehouse_id', $warehouseId)->where('product_id', $itemId)->where('batch_no', $batchNo)->first();
        $qtyOnHand = max(0, ((float) ($batch->qty_on_hand ?? 0)) - $qty);
        $value = max(0, ((float) ($batch->stock_value ?? 0)) - ($qty * $unitCost));

        DB::table('inv_batches')->updateOrInsert(
            ['company_id' => 1, 'warehouse_id' => $warehouseId, 'product_id' => $itemId, 'batch_no' => $batchNo],
            ['expired_date' => $expiredDate, 'unit_cost' => $unitCost, 'qty_on_hand' => $qtyOnHand, 'stock_value' => $value, 'status' => $qtyOnHand > 0 ? 'active' : 'depleted', 'updated_at' => now(), 'created_at' => now()]
        );
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

    private function resolveWarehouseId(object $header): int
    {
        foreach (['warehouse_id', 'gudang_id', 'id_gudang', 'id_warehouse'] as $column) {
            if (property_exists($header, $column) && ! empty($header->{$column})) {
                return (int) $header->{$column};
            }
        }

        foreach (['warehouse_code', 'kode_gudang', 'warehouse', 'gudang'] as $column) {
            if (property_exists($header, $column) && ! empty($header->{$column})) {
                return (int) DB::table('warehouses')->where('code', (string) $header->{$column})->value('id');
            }
        }

        return 0;
    }

    private function resolveColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function filterColumns(string $table, array $payload): array
    {
        $validColumns = array_flip(Schema::getColumnListing($table));

        return array_filter(
            $payload,
            fn (string $column): bool => isset($validColumns[$column]),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
