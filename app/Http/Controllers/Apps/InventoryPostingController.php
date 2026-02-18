<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\OpeningBalanceRequest;
use App\Models\Inventory\Item;
use App\Services\Inventory\BatchAllocationService;
use App\Services\Inventory\StockService;
use App\Services\Inventory\UomConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

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
            new Middleware('permission:inventory-posting-opening-balance', only: ['postOpeningBalance']),
        ];
    }

    public function postOpeningBalance(OpeningBalanceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $qtyBase = $this->resolveQtyBase((int) $validated['item_id'], (int) $validated['uom_id'], (float) $validated['qty'], 0);

        $ledger = $this->stockService->postMutation([
            'trx_type' => 'OPENING_BALANCE',
            'trx_id' => (int) now()->format('YmdHis'),
            'warehouse_id' => $validated['warehouse_id'],
            'item_id' => $validated['item_id'],
            'batch_id' => $validated['batch_id'] ?? null,
            'qty_base' => $qtyBase,
            'uom_id' => $validated['uom_id'],
            'qty_input' => $validated['qty'],
            'unit_cost' => $validated['unit_cost'],
            'trx_datetime' => $validated['trx_datetime'] ?? now(),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Opening balance posted',
            'id' => $ledger->id,
        ]);
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

    private function resolveQtyBase(int $itemId, int $uomId, float $qtyInput, float $qtyBase): float
    {
        if ($qtyBase > 0 || $qtyBase < 0) {
            return $qtyBase;
        }

        return $this->uomConversionService->toBase($itemId, $uomId, $qtyInput);
    }
}
