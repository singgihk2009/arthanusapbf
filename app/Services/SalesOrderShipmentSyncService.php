<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesOrderShipmentSyncService
{
    public function syncFromInternalUsage(int|object $internalUsage, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($internalUsage, $userId): bool {
            $dispatch = is_object($internalUsage)
                ? DB::table('internal_usages')->lockForUpdate()->where('id', (int) $internalUsage->id)->first()
                : DB::table('internal_usages')->lockForUpdate()->where('id', (int) $internalUsage)->first();
            abort_unless($dispatch, 404, 'Dispatch not found.');

            $status = strtolower((string) ($dispatch->status ?? ''));
            $hasSalesReference = strtolower((string) ($dispatch->source_type ?? '')) === 'sales_order' || ! empty($dispatch->sale_id);
            if (! in_array($status, ['posted', 'completed'], true) || ! $hasSalesReference) {
                return false;
            }

            if (! empty($dispatch->sales_order_synced_at)) {
                return false;
            }

            $saleId = (int) ($dispatch->sale_id ?? $dispatch->source_id ?? 0);
            abort_if($saleId <= 0, 422, 'Sales Order dispatch reference is invalid.');
            $dispatchId = (int) $dispatch->id;

            $sale = DB::table('sales')->lockForUpdate()->where('id', $saleId)->first();
            abort_unless($sale, 422, 'Referenced Sales Order not found.');

            $lines = DB::table('internal_usage_lines')->where('internal_usage_id', $dispatchId)->get();

            foreach ($lines as $line) {
                $saleLineId = (int) ($line->sale_line_id ?? $line->source_line_id ?? 0);
                abort_if($saleLineId <= 0, 422, 'Sales Order line reference is required for shipment.');

                $saleLine = DB::table('sales_lines')->lockForUpdate()->where('id', $saleLineId)->first();
                abort_if(! $saleLine || (int) $saleLine->sale_id !== $saleId, 422, 'Sales Order line does not belong to the Sales Order.');

                $lineQty = (float) ($line->qty ?? $line->quantity ?? $line->qty_used ?? $line->qty_out ?? $line->issued_qty ?? 0);
                abort_if($lineQty <= 0, 422, 'Shipment quantity must be greater than zero.');

                $newShipped = (float) $saleLine->qty_shipped + $lineQty;
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
                'sales_order_synced_by' => $userId,
                'updated_at' => now(),
            ]);

            return true;
        });
    }
}
