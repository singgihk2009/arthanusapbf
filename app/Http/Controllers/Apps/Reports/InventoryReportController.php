<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLedger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class InventoryReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:report-stock-balance', only: ['stockBalance']),
            new Middleware('permission:report-stock-card', only: ['stockCard']),
            new Middleware('permission:report-expired-soon', only: ['expiredSoon']),
            new Middleware('permission:report-minimum-stock-alerts', only: ['minimumStockAlerts']),
        ];
    }

    public function stockBalance(Request $request): JsonResponse
    {
        $data = DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->leftJoin('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->when($request->integer('warehouse_id'), fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->when($request->integer('item_id'), fn ($q, $v) => $q->where('stock_balances.item_id', $v))
            ->select([
                'stock_balances.warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'stock_balances.item_id',
                'items.sku',
                'items.name as item_name',
                'stock_balances.batch_id',
                'item_batches.batch_no',
                'item_batches.expired_date',
                'stock_balances.on_hand_base',
                'stock_balances.reserved_base',
            ])
            ->orderBy('warehouses.code')
            ->orderBy('items.sku')
            ->paginate(50)
            ->withQueryString();
        return response()->json($data);
    }

    public function stockCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['nullable', 'integer'],
            'item_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $openingBalance = (float) StockLedger::query()
            ->where('item_id', $validated['item_id'])
            ->when(! empty($validated['warehouse_id']), fn ($q) => $q->where('warehouse_id', $validated['warehouse_id']))
            ->where('trx_datetime', '<', $start)
            ->sum('qty_base');

        $movements = StockLedger::query()
            ->where('item_id', $validated['item_id'])
            ->when(! empty($validated['warehouse_id']), fn ($q) => $q->where('warehouse_id', $validated['warehouse_id']))
            ->whereBetween('trx_datetime', [$start, $end])
            ->orderBy('trx_datetime')
            ->orderBy('id')
            ->get();

        $running = $openingBalance;

        $rows = $movements->map(function (StockLedger $ledger) use (&$running) {
            $running += (float) $ledger->qty_base;

            return [
                'id' => $ledger->id,
                'trx_datetime' => $ledger->trx_datetime,
                'trx_type' => $ledger->trx_type,
                'trx_id' => $ledger->trx_id,
                'batch_id' => $ledger->batch_id,
                'qty_base' => (float) $ledger->qty_base,
                'running_balance' => $running,
            ];
        });

        return response()->json([
            'warehouse_id' => ! empty($validated['warehouse_id']) ? (int) $validated['warehouse_id'] : null,
            'item_id' => (int) $validated['item_id'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'rows' => $rows,
        ]);
    }

    public function expiredSoon(Request $request): JsonResponse
    {
        $days = max(1, (int) $request->integer('days', 30));
        $today = now()->startOfDay();
        $limitDate = now()->addDays($days)->endOfDay();

        $data = DB::table('stock_balances')
            ->join('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('stock_balances.on_hand_base', '>', 0)
            ->where('items.track_expired', true)
            ->whereNotNull('item_batches.expired_date')
            ->whereDate('item_batches.expired_date', '<=', $limitDate->toDateString())
            ->when($request->integer('warehouse_id'), fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->selectRaw('warehouses.code as warehouse_code, warehouses.name as warehouse_name, items.sku, items.name as item_name, item_batches.batch_no, item_batches.expired_date, stock_balances.on_hand_base, DATEDIFF(item_batches.expired_date, CURDATE()) as days_left')
            ->orderBy('item_batches.expired_date')
            ->paginate(50)
            ->withQueryString();

        $data->setCollection($data->getCollection()->map(function ($row) {
            $daysLeft = (int) $row->days_left;
            $row->status = $daysLeft < 0 ? 'EXPIRED' : ($daysLeft <= 7 ? 'KRITIS' : 'PERINGATAN');

            return $row;
        }));

        return response()->json($data);
    }

    public function minimumStockAlerts(Request $request): JsonResponse
    {
        $rows = DB::table('warehouse_item_settings as wis')
            ->join('warehouses as w', 'w.id', '=', 'wis.warehouse_id')
            ->join('items as i', 'i.id', '=', 'wis.item_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.warehouse_id', '=', 'wis.warehouse_id')
                    ->on('sb.item_id', '=', 'wis.item_id');
            })
            ->when($request->integer('warehouse_id'), fn ($q, $v) => $q->where('wis.warehouse_id', $v))
            ->groupBy('wis.warehouse_id', 'wis.item_id', 'wis.min_stock_base', 'w.code', 'w.name', 'i.sku', 'i.name')
            ->havingRaw('COALESCE(SUM(sb.on_hand_base), 0) <= wis.min_stock_base')
            ->selectRaw('wis.warehouse_id, wis.item_id, wis.min_stock_base, w.code as warehouse_code, w.name as warehouse_name, i.sku, i.name as item_name, COALESCE(SUM(sb.on_hand_base), 0) as on_hand_base')
            ->orderBy('w.code')
            ->orderBy('i.sku')
            ->paginate(50)
            ->withQueryString();

        return response()->json($rows);
    }
}
