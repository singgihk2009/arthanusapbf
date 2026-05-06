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
            new Middleware('permission:inventory-posting-grn', only: ['postGoodsReceipt', 'postReceivingEntry', 'unpostReceivingEntry']),
            new Middleware('permission:inventory-posting-transfer', only: ['postTransfer', 'unpostTransfer']),
            new Middleware('permission:inventory-posting-sale', only: ['postSale']),
            new Middleware('permission:inventory-posting-usage', only: ['postInternalUsage', 'unpostInternalUsage']),
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
            $unitCostBase = $this->resolveUnitCostPerBase((float) $line->unit_price, (float) $line->qty_received, $qtyBase);

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
                'unit_cost' => $unitCostBase,
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
        $lineForeignKey = $this->resolveColumn('receiving_entry_lines', ['receiving_entry_id', 'receiving_id', 'entry_id', 'header_id']) ?? 'receiving_entry_id';
        $batchColumn = $this->resolveColumn('receiving_entry_lines', ['batch_number', 'batch_no', 'no_batch']) ?? 'batch_number';
        DB::transaction(function () use ($receivingEntry, $lineForeignKey, $batchColumn, $request): void {
            $header = DB::table('receiving_entries')->where('id', $receivingEntry)->lockForUpdate()->first();
            abort_unless($header, 404, 'Receiving entry not found');
            abort_if(strtolower((string) ($header->status ?? '')) === 'posted', 422, 'Receiving sudah posted dan tidak bisa diposting ulang.');

            $warehouseId = $this->resolveWarehouseId($header);
            abort_if(! $warehouseId, 422, 'Warehouse receiving entry tidak valid.');

            $lines = DB::table('receiving_entry_lines')->where($lineForeignKey, $receivingEntry)->get();

            foreach ($lines as $line) {
                $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty, 0);
                $unitCostBase = $this->resolveUnitCostPerBase((float) $line->price, (float) $line->qty, $qtyBase);
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

                if (($header->source_type ?? null) === 'purchase_order') {
                    $sourceItemId = (int) ($line->source_item_id ?? 0);
                    abort_if($sourceItemId <= 0, 422, 'source_item_id wajib untuk receiving PO.');
                    $poItem = DB::table('purchase_order_items')->where('id', $sourceItemId)->lockForUpdate()->first();
                    abort_if(! $poItem || (int) $poItem->purchase_order_id !== (int) $header->source_id, 422, 'Item PO tidak valid.');
                    $latestReceived = (float) ($poItem->received_qty ?? $poItem->qty_received ?? 0);
                    $latestRemaining = max(0, (float) $poItem->qty_ordered - $latestReceived);
                    abort_if((float) $line->qty > $latestRemaining, 422, 'Qty receiving melebihi sisa qty PO terbaru.');

                    $newReceived = $latestReceived + (float) $line->qty;
                    $newRemaining = max(0, (float) $poItem->qty_ordered - $newReceived);
                    DB::table('purchase_order_items')->where('id', $sourceItemId)->update([
                        'received_qty' => $newReceived,
                        'qty_received' => $newReceived,
                        'remaining_qty' => $newRemaining,
                        'updated_at' => now(),
                    ]);
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
                    'unit_cost' => $unitCostBase,
                    'created_by' => $request->user()?->id,
                ]);
            }

            if (($header->source_type ?? null) === 'purchase_order') {
                $poItems = DB::table('purchase_order_items')->where('purchase_order_id', $header->source_id)->get();
                $hasReceived = $poItems->contains(fn ($item) => (float) ($item->received_qty ?? $item->qty_received ?? 0) > 0);
                $allDone = $poItems->every(fn ($item) => ((float) ($item->remaining_qty ?? ((float) $item->qty_ordered - (float) ($item->received_qty ?? $item->qty_received ?? 0))) <= 0) || (bool) ($item->is_closed ?? false));
                $fulfillmentStatus = $allDone ? 'fully_received' : ($hasReceived ? 'partially_received' : 'open');
                DB::table('purchase_orders')->where('id', $header->source_id)->update([
                    'fulfillment_status' => $fulfillmentStatus,
                    'status' => $fulfillmentStatus,
                    'updated_at' => now(),
                ]);
            }

            DB::table('receiving_entries')->where('id', $receivingEntry)->update($this->filterColumns('receiving_entries', [
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $request->user()?->id,
                'updated_at' => now(),
            ]));
        });

        return response()->json(['message' => 'Receiving entry posted', 'id' => $receivingEntry]);
    }

    public function unpostReceivingEntry(Request $request, int $receivingEntry): JsonResponse
    {
        $header = DB::table('receiving_entries')->where('id', $receivingEntry)->first();
        abort_unless($header, 404, 'Receiving entry not found');
        abort_if(($header->status ?? null) !== 'POSTED', 422, 'Receiving entry belum diposting');

        $this->createReversalStockLedgers(['RCV_IN'], $receivingEntry, $request->user()?->id);

        DB::table('receiving_entries')->where('id', $receivingEntry)->update($this->filterColumns('receiving_entries', [
            'status' => 'DRAFT',
            'posted_at' => null,
            'posted_by' => null,
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Receiving entry unposted', 'id' => $receivingEntry]);
    }

    public function postTransfer(Request $request, int $transferId): JsonResponse
    {
        $header = DB::table('warehouse_transfers')->where('id', $transferId)->first();
        abort_unless($header, 404, 'Transfer not found');
        abort_if($header->status === 'RECEIVED', 422, 'Transfer already received/posted');

        $lines = DB::table('warehouse_transfer_lines')->where('warehouse_transfer_id', $transferId)->get();

        foreach ($lines as $line) {
            $qtyBase = $this->resolveQtyBase((int) $line->item_id, (int) $line->uom_id, (float) $line->qty_requested, (float) $line->qty_base);
            $batchId = isset($line->batch_id) && $line->batch_id ? (int) $line->batch_id : null;
            $unitCost = $batchId
                ? $this->resolveBatchCost((int) $header->from_warehouse_id, (int) $line->item_id, $batchId)
                : $this->resolveAverageCost((int) $header->from_warehouse_id, (int) $line->item_id);

            $this->stockService->postMutation([
                'trx_type' => 'TRANSFER_OUT',
                'trx_id' => $transferId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->from_warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $batchId,
                'qty_base' => -1 * $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_requested,
                'unit_cost' => $unitCost,
                'created_by' => $request->user()?->id,
            ]);

            $this->stockService->postMutation([
                'trx_type' => 'TRANSFER_IN',
                'trx_id' => $transferId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->to_warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $batchId,
                'qty_base' => $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_requested,
                'unit_cost' => $unitCost,
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

    public function unpostTransfer(Request $request, int $transferId): JsonResponse
    {
        $header = DB::table('warehouse_transfers')->where('id', $transferId)->first();
        abort_unless($header, 404, 'Transfer not found');
        abort_if($header->status !== 'RECEIVED', 422, 'Transfer belum diposting');

        $this->createReversalStockLedgers(['TRANSFER_OUT', 'TRANSFER_IN'], $transferId, $request->user()?->id);

        DB::table('warehouse_transfers')->where('id', $transferId)->update($this->filterColumns('warehouse_transfers', [
            'status' => 'DRAFT',
            'received_at' => null,
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Transfer unposted', 'id' => $transferId]);
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
            $batchId = isset($line->batch_id) && $line->batch_id ? (int) $line->batch_id : null;

            if ($batchId) {
                $isValidBatch = DB::table('item_batches')
                    ->where('id', $batchId)
                    ->where('item_id', $line->item_id)
                    ->exists();

                abort_if(! $isValidBatch, 422, 'Batch number tidak valid untuk item ini.');
            }

            $unitCost = $batchId
                ? $this->resolveBatchCost((int) $header->warehouse_id, (int) $line->item_id, $batchId)
                : $this->resolveAverageCost((int) $header->warehouse_id, (int) $line->item_id);

            $this->stockService->postMutation([
                'trx_type' => 'USAGE_OUT',
                'trx_id' => $usageId,
                'trx_line_id' => $line->id,
                'warehouse_id' => $header->warehouse_id,
                'item_id' => $line->item_id,
                'batch_id' => $batchId,
                'qty_base' => -1 * $qtyBase,
                'uom_id' => $line->uom_id,
                'qty_input' => $line->qty_used,
                'unit_cost' => $unitCost,
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

    public function unpostInternalUsage(Request $request, int $usageId): JsonResponse
    {
        $header = DB::table('internal_usages')->where('id', $usageId)->first();
        abort_unless($header, 404, 'Internal usage not found');
        abort_if($header->status !== 'POSTED', 422, 'Internal usage belum diposting');

        $integrationTransaction = DB::table('inv_transactions')
            ->where('source_table', 'internal_usages')
            ->where('source_id', $usageId)
            ->first();

        if ($integrationTransaction) {
            abort_if($integrationTransaction->gl_status === 'posted', 422, 'Dokumen sudah diposting ke Finance Hub, gunakan reversal.');

            DB::table('integration_outbox')
                ->where('aggregate_type', 'inv_transaction')
                ->where('aggregate_id', $integrationTransaction->id)
                ->delete();

            DB::table('inv_transactions')->where('id', $integrationTransaction->id)->delete();
        }

        $this->createReversalStockLedgers(['USAGE_OUT'], $usageId, $request->user()?->id);

        DB::table('internal_usages')->where('id', $usageId)->update($this->filterColumns('internal_usages', [
            'status' => 'DRAFT',
            'posted_at' => null,
            'posted_by' => null,
            'updated_at' => now(),
        ]));

        return response()->json(['message' => 'Internal usage unposted', 'id' => $usageId]);
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
            ->select('l.id', 'l.item_id', 'l.batch_id', 'l.qty_used', 'l.uom_id', 'l.qty_base', 'l.notes', 'i.track_expired')
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

            $batchId = $line->batch_id ? (int) $line->batch_id : null;
            $valuation = $batchId ? 'BATCH' : 'AVG';
            $unitCost = $batchId
                ? $this->resolveBatchCost($warehouseId, (int) $line->item_id, $batchId)
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

        $replayed = $this->replayAverageCostFromLedgers($warehouseId, $itemId);
        if ($replayed > 0) {
            return $replayed;
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

        $replayed = $this->replayAverageCostFromLedgers($warehouseId, $itemId, $batchId);
        if ($replayed > 0) {
            return $replayed;
        }

        $fallback = DB::table('stock_ledgers')->where('warehouse_id', $warehouseId)->where('item_id', $itemId)->where('batch_id', $batchId)->whereNotNull('unit_cost')->orderByDesc('id')->value('unit_cost');

        return (float) ($fallback ?? 0);
    }

    private function replayAverageCostFromLedgers(int $warehouseId, int $itemId, ?int $batchId = null): float
    {
        $ledgers = DB::table('stock_ledgers')
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->when($batchId, fn ($query, $value) => $query->where('batch_id', $value))
            ->orderBy('trx_datetime')
            ->orderBy('id')
            ->get(['qty_base', 'unit_cost']);

        if ($ledgers->isEmpty()) {
            return 0;
        }

        $onHand = 0.0;
        $stockValue = 0.0;

        foreach ($ledgers as $row) {
            $qtyDelta = (float) $row->qty_base;
            $inputCost = (float) ($row->unit_cost ?? 0);
            $runningAvg = $onHand > 0 ? ($stockValue / $onHand) : 0.0;

            if ($qtyDelta > 0) {
                $effectiveCost = $inputCost > 0 ? $inputCost : $runningAvg;
                $onHand += $qtyDelta;
                $stockValue += ($qtyDelta * $effectiveCost);

                continue;
            }

            if ($qtyDelta < 0) {
                $issuedQty = abs($qtyDelta);
                $effectiveCost = $inputCost > 0 ? $inputCost : $runningAvg;
                $onHand = max(0, $onHand - $issuedQty);
                $stockValue = max(0, $stockValue - ($issuedQty * $effectiveCost));
            }
        }

        return $onHand > 0 ? ($stockValue / $onHand) : 0;
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

    private function resolveUnitCostPerBase(float $unitCostInput, float $qtyInput, float $qtyBase): float
    {
        if ($qtyInput == 0.0) {
            return $unitCostInput;
        }

        $conversionFactor = abs($qtyBase / $qtyInput);

        if ($conversionFactor <= 0.0) {
            return $unitCostInput;
        }

        return $unitCostInput / $conversionFactor;
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

    private function createReversalStockLedgers(array $trxTypes, int $trxId, ?int $userId): void
    {
        $ledgers = DB::table('stock_ledgers')
            ->whereIn('trx_type', $trxTypes)
            ->where('trx_id', $trxId)
            ->orderBy('id')
            ->get();

        abort_if($ledgers->isEmpty(), 422, 'Data stock ledger tidak ditemukan untuk unpost.');

        foreach ($ledgers as $ledger) {
            $this->stockService->postMutation([
                'trx_type' => (string) $ledger->trx_type,
                'trx_id' => (int) $ledger->trx_id,
                'trx_line_id' => $ledger->trx_line_id,
                'warehouse_id' => (int) $ledger->warehouse_id,
                'item_id' => (int) $ledger->item_id,
                'batch_id' => $ledger->batch_id ? (int) $ledger->batch_id : null,
                'qty_base' => -1 * (float) $ledger->qty_base,
                'uom_id' => (int) $ledger->uom_id,
                'qty_input' => (float) $ledger->qty_input,
                'unit_cost' => $ledger->unit_cost !== null ? (float) $ledger->unit_cost : null,
                'created_by' => $userId,
            ]);
        }
    }
}
