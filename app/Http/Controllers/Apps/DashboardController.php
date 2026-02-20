<?php

namespace App\Http\Controllers\Apps;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller implements HasMiddleware
{
    /**
     * middleware
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard-data'),
        ];
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $today = now()->toDateString();

        $totals = [
            'warehouses' => DB::table('warehouses')->count(),
            'items' => DB::table('items')->count(),
            'on_hand_qty' => (float) DB::table('stock_balances')->sum('on_hand_base'),
            'low_stock_count' => DB::table('warehouse_item_settings as wis')
                ->leftJoin('stock_balances as sb', function ($join) {
                    $join->on('sb.warehouse_id', '=', 'wis.warehouse_id')
                        ->on('sb.item_id', '=', 'wis.item_id');
                })
                ->groupBy('wis.warehouse_id', 'wis.item_id', 'wis.min_stock_base')
                ->havingRaw('COALESCE(SUM(sb.on_hand_base), 0) <= wis.min_stock_base')
                ->get()
                ->count(),
        ];

        $inboundToday = (float) DB::table('stock_ledgers')
            ->whereDate('trx_datetime', $today)
            ->where('qty_base', '>', 0)
            ->sum('qty_base');

        $outboundToday = abs((float) DB::table('stock_ledgers')
            ->whereDate('trx_datetime', $today)
            ->where('qty_base', '<', 0)
            ->sum('qty_base'));

        $stockByWarehouse = DB::table('stock_balances as sb')
            ->join('warehouses as w', 'w.id', '=', 'sb.warehouse_id')
            ->groupBy('w.id', 'w.code', 'w.name')
            ->selectRaw('w.id, w.code, w.name, SUM(sb.on_hand_base) as on_hand_qty')
            ->orderByDesc('on_hand_qty')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'warehouse' => trim($row->code . ' - ' . $row->name),
                'on_hand_qty' => (float) $row->on_hand_qty,
            ])
            ->values();

        $lowStockItems = DB::table('warehouse_item_settings as wis')
            ->join('warehouses as w', 'w.id', '=', 'wis.warehouse_id')
            ->join('items as i', 'i.id', '=', 'wis.item_id')
            ->leftJoin('stock_balances as sb', function ($join) {
                $join->on('sb.warehouse_id', '=', 'wis.warehouse_id')
                    ->on('sb.item_id', '=', 'wis.item_id');
            })
            ->groupBy('wis.warehouse_id', 'wis.item_id', 'wis.min_stock_base', 'w.code', 'w.name', 'i.sku', 'i.name')
            ->havingRaw('COALESCE(SUM(sb.on_hand_base), 0) <= wis.min_stock_base')
            ->selectRaw('w.code as warehouse_code, w.name as warehouse_name, i.sku, i.name as item_name, wis.min_stock_base, COALESCE(SUM(sb.on_hand_base), 0) as on_hand_qty')
            ->orderByRaw('(COALESCE(SUM(sb.on_hand_base), 0) - wis.min_stock_base) asc')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'warehouse' => $row->warehouse_code,
                'item' => $row->sku . ' - ' . $row->item_name,
                'on_hand_qty' => (float) $row->on_hand_qty,
                'min_stock_qty' => (float) $row->min_stock_base,
                'gap_qty' => (float) $row->on_hand_qty - (float) $row->min_stock_base,
            ])
            ->values();

        $movementTrend = collect(range(5, 0))
            ->map(function (int $monthsAgo) {
                $start = Carbon::now()->subMonths($monthsAgo)->startOfMonth();
                $end = Carbon::now()->subMonths($monthsAgo)->endOfMonth();

                $inbound = (float) DB::table('stock_ledgers')
                    ->whereBetween('trx_datetime', [$start, $end])
                    ->where('qty_base', '>', 0)
                    ->sum('qty_base');

                $outbound = abs((float) DB::table('stock_ledgers')
                    ->whereBetween('trx_datetime', [$start, $end])
                    ->where('qty_base', '<', 0)
                    ->sum('qty_base'));

                return [
                    'label' => $start->translatedFormat('M Y'),
                    'inbound_qty' => $inbound,
                    'outbound_qty' => $outbound,
                ];
            })
            ->push([
                'label' => Carbon::now()->translatedFormat('M Y'),
                'inbound_qty' => $inboundToday,
                'outbound_qty' => $outboundToday,
            ])
            ->values();

        return inertia('Apps/Dashboard', [
            'kpi' => [
                ...$totals,
                'inbound_today' => $inboundToday,
                'outbound_today' => $outboundToday,
                'last_sync_at' => now()->toDateTimeString(),
            ],
            'stock_by_warehouse' => $stockByWarehouse,
            'low_stock_items' => $lowStockItems,
            'movement_trend' => $movementTrend,
        ]);
    }
}
