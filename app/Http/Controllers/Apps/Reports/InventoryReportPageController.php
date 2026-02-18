<?php

namespace App\Http\Controllers\Apps\Reports;

use App\Http\Controllers\Controller;
use App\Models\Inventory\StockLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class InventoryReportPageController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory-reports-access'),
        ];
    }

    public function __invoke(Request $request)
    {
        $type = $request->string('type')->toString() ?: 'stock-balance';
        $allowed = ['stock-balance', 'stock-card', 'expired-soon', 'minimum-stock-alerts'];

        if (! in_array($type, $allowed, true)) {
            $type = 'stock-balance';
        }

        $filters = [
            'type' => $type,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'item_id' => $request->integer('item_id') ?: null,
            'start_date' => $request->string('start_date')->toString() ?: now()->startOfMonth()->toDateString(),
            'end_date' => $request->string('end_date')->toString() ?: now()->toDateString(),
            'days' => $request->integer('days', 30),
        ];

        $reportData = $this->resolveReportData($filters);

        return inertia('Apps/Reports/Inventory/Index', [
            'filters' => $filters,
            'warehouses' => DB::table('warehouses')->select('id', 'code', 'name')->orderBy('code')->get(),
            'items' => DB::table('items')->select('id', 'sku', 'name')->orderBy('sku')->limit(300)->get(),
            'reportData' => $reportData,
        ]);
    }

    private function resolveReportData(array $filters): array
    {
        return match ($filters['type']) {
            'stock-card' => $this->stockCardData($filters),
            'expired-soon' => $this->expiredSoonData($filters),
            'minimum-stock-alerts' => $this->minimumStockData($filters),
            default => $this->stockBalanceData($filters),
        };
    }

    private function stockBalanceData(array $filters): array
    {
        $rows = DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->leftJoin('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->when($filters['item_id'], fn ($q, $v) => $q->where('stock_balances.item_id', $v))
            ->select([
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'item_batches.batch_no',
                'item_batches.expired_date',
                'stock_balances.on_hand_base',
                'stock_balances.reserved_base',
            ])
            ->orderBy('warehouses.code')
            ->orderBy('items.sku')
            ->limit(300)
            ->get();

        return [
            'title' => 'Stock Balance',
            'rows' => $rows,
        ];
    }

    private function stockCardData(array $filters): array
    {
        if (! $filters['warehouse_id'] || ! $filters['item_id']) {
            return [
                'title' => 'Stock Card',
                'opening_balance' => 0,
                'closing_balance' => 0,
                'rows' => [],
            ];
        }

        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $end = Carbon::parse($filters['end_date'])->endOfDay();

        $opening = (float) StockLedger::query()
            ->where('warehouse_id', $filters['warehouse_id'])
            ->where('item_id', $filters['item_id'])
            ->where('trx_datetime', '<', $start)
            ->sum('qty_base');

        $movements = StockLedger::query()
            ->where('warehouse_id', $filters['warehouse_id'])
            ->where('item_id', $filters['item_id'])
            ->whereBetween('trx_datetime', [$start, $end])
            ->orderBy('trx_datetime')
            ->orderBy('id')
            ->limit(500)
            ->get();

        $running = $opening;
        $rows = $movements->map(function (StockLedger $ledger) use (&$running) {
            $running += (float) $ledger->qty_base;

            return [
                'trx_datetime' => optional($ledger->trx_datetime)->format('Y-m-d H:i:s'),
                'trx_type' => $ledger->trx_type,
                'qty_base' => (float) $ledger->qty_base,
                'running_balance' => $running,
            ];
        });

        return [
            'title' => 'Stock Card',
            'opening_balance' => $opening,
            'closing_balance' => $running,
            'rows' => $rows,
        ];
    }

    private function expiredSoonData(array $filters): array
    {
        $days = max(1, (int) $filters['days']);
        $today = now()->toDateString();
        $limit = now()->addDays($days)->toDateString();

        $rows = DB::table('stock_balances')
            ->join('item_batches', 'item_batches.id', '=', 'stock_balances.batch_id')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('stock_balances.on_hand_base', '>', 0)
            ->whereBetween('item_batches.expired_date', [$today, $limit])
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('stock_balances.warehouse_id', $v))
            ->select([
                'warehouses.code as warehouse_code',
                'items.sku',
                'items.name as item_name',
                'item_batches.batch_no',
                'item_batches.expired_date',
                'stock_balances.on_hand_base',
            ])
            ->orderBy('item_batches.expired_date')
            ->limit(300)
            ->get();

        return [
            'title' => 'Expired Soon',
            'rows' => $rows,
        ];
    }

    private function minimumStockData(array $filters): array
    {
        $rows = DB::table('warehouse_item_settings as wis')
            ->join('warehouses as w', 'w.id', '=', 'wis.warehouse_id')
            ->join('items as i', 'i.id', '=', 'wis.item_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.warehouse_id', '=', 'wis.warehouse_id')
                    ->on('sb.item_id', '=', 'wis.item_id');
            })
            ->when($filters['warehouse_id'], fn ($q, $v) => $q->where('wis.warehouse_id', $v))
            ->groupBy('w.code', 'i.sku', 'i.name', 'wis.min_stock_base')
            ->havingRaw('COALESCE(SUM(sb.on_hand_base), 0) <= wis.min_stock_base')
            ->selectRaw('w.code as warehouse_code, i.sku, i.name as item_name, wis.min_stock_base, COALESCE(SUM(sb.on_hand_base), 0) as on_hand_base')
            ->orderBy('w.code')
            ->orderBy('i.sku')
            ->limit(300)
            ->get();

        return [
            'title' => 'Minimum Stock Alerts',
            'rows' => $rows,
        ];
    }
}
