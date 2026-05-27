<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesOrderShipmentSyncService
{
    public function syncFromDispatch(int $dispatchId): void
    {
        DB::transaction(function () use ($dispatchId): void {
            $dispatch = DB::table('internal_usages')->lockForUpdate()->where('id', $dispatchId)->first();
            abort_unless($dispatch, 404, 'Dispatch not found.');

            if (($dispatch->source_type ?? null) !== 'sales_order') {
                return;
            }

            if (! empty($dispatch->sales_order_synced_at)) {
                return;
            }

            $saleId = (int) ($dispatch->sale_id ?? $dispatch->source_id ?? 0);
            abort_if($saleId <= 0, 422, 'Sales Order dispatch reference is invalid.');
            abort_if(strtoupper((string) ($dispatch->status ?? '')) !== 'POSTED', 422, 'Dispatch must be posted before shipment sync.');

            $sale = DB::table('sales')->lockForUpdate()->where('id', $saleId)->first();
            abort_unless($sale, 422, 'Referenced Sales Order not found.');

            $lines = DB::table('internal_usage_lines')->where('internal_usage_id', $dispatchId)->get();

            foreach ($lines as $line) {
                $saleLineId = (int) ($line->sale_line_id ?? $line->source_line_id ?? 0);
                abort_if($saleLineId <= 0, 422, 'Sales Order line reference is required for shipment.');

                $saleLine = DB::table('sales_lines')->lockForUpdate()->where('id', $saleLineId)->first();
                abort_if(! $saleLine || (int) $saleLine->sale_id !== $saleId, 422, 'Sales Order line does not belong to the Sales Order.');

                $newShipped = (float) $saleLine->qty_shipped + (float) $line->qty_used;
                abort_if($newShipped - (float) $saleLine->qty_sold > 0.0001, 422, 'Shipment quantity cannot exceed remaining Sales Order quantity.');

                DB::table('sales_lines')->where('id', $saleLineId)->update([
                    'qty_shipped' => $newShipped,
                    'updated_at' => now(),
                ]);
            }

            $totals = DB::table('sales_lines')
                ->where('sale_id', $saleId)
                ->selectRaw('COALESCE(SUM(qty_sold),0) as ordered_total, COALESCE(SUM(qty_shipped),0) as shipped_total')
                ->first();

            $ordered = (float) ($totals->ordered_total ?? 0);
            $shipped = (float) ($totals->shipped_total ?? 0);
            $nextStatus = $shipped <= 0 ? 'approved' : ($shipped + 0.0001 >= $ordered ? 'fully_shipped' : 'partially_shipped');

            DB::table('sales')->where('id', $saleId)->update([
                'status' => $nextStatus,
                'updated_at' => now(),
            ]);

            DB::table('internal_usages')->where('id', $dispatchId)->update([
                'sales_order_synced_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}

