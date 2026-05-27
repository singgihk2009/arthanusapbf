<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalesOrderShipmentSyncService
{
    public function syncFromInternalUsage(int|object $internalUsage, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($internalUsage, $userId): bool {
            $dispatch = is_object($internalUsage)
                ? DB::table('internal_usages')->lockForUpdate()->where('id', (int) $internalUsage->id)->first()
                : DB::table('internal_usages')->lockForUpdate()->where('id', (int) $internalUsage)->first();

            if (! $dispatch) {
                throw new RuntimeException('Dispatch not found.');
            }

            $status = strtolower((string) ($dispatch->status ?? ''));
            $hasSalesReference = strtolower((string) ($dispatch->source_type ?? '')) === 'sales_order' || ! empty($dispatch->sale_id);
            if (! in_array($status, ['posted', 'completed'], true) || ! $hasSalesReference) {
                return false;
            }

            if (! empty($dispatch->sales_order_synced_at)) {
                return false;
            }

            $saleId = (int) ($dispatch->sale_id ?: (strtolower((string) ($dispatch->source_type ?? '')) === 'sales_order' ? $dispatch->source_id : 0));
            if ($saleId <= 0) {
                throw new RuntimeException('Sales Order dispatch reference is invalid.');
            }

            $sale = DB::table('sales')->lockForUpdate()->where('id', $saleId)->first();
            if (! $sale) {
                throw new RuntimeException('Referenced Sales Order not found.');
            }

            $dispatchId = (int) $dispatch->id;
            $dispatchLines = DB::table('internal_usage_lines')->where('internal_usage_id', $dispatchId)->get();
            $saleLines = DB::table('sales_lines')->lockForUpdate()->where('sale_id', $saleId)->get();

            foreach ($dispatchLines as $line) {
                $qty = $this->resolveLineQty($line);
                if ($qty <= 0) {
                    continue;
                }

                $saleLine = $this->resolveSaleLine($line, $saleId, $saleLines);
                $newShipped = (float) $saleLine->qty_shipped + $qty;
                if ($newShipped - (float) $saleLine->qty_sold > 0.0001) {
                    throw new RuntimeException(sprintf('Dispatch %s skipped: shipment qty would exceed SO remaining qty.', (string) ($dispatch->number ?? $dispatchId)));
                }

                DB::table('sales_lines')->where('id', $saleLine->id)->update([
                    'qty_shipped' => $newShipped,
                    'updated_at' => now(),
                ]);

                $saleLines = $saleLines->map(function ($existing) use ($saleLine, $newShipped) {
                    if ((int) $existing->id === (int) $saleLine->id) {
                        $existing->qty_shipped = $newShipped;
                    }
                    return $existing;
                });
            }

            $ordered = (float) $saleLines->sum(fn ($line) => (float) $line->qty_sold);
            $shipped = (float) $saleLines->sum(fn ($line) => (float) $line->qty_shipped);
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

    private function resolveLineQty(object $line): float
    {
        foreach (['qty_used', 'qty', 'quantity', 'qty_out'] as $field) {
            $value = $line->{$field} ?? null;
            if ($value !== null) {
                return (float) $value;
            }
        }

        return (float) ($line->qty_base ?? 0);
    }

    private function resolveSaleLine(object $line, int $saleId, Collection $saleLines): object
    {
        $saleLineId = (int) ($line->sale_line_id ?? 0);
        if ($saleLineId > 0) {
            $saleLine = $saleLines->firstWhere('id', $saleLineId);
            if (! $saleLine) {
                throw new RuntimeException('Sales Order line does not belong to the Sales Order.');
            }
            return $saleLine;
        }

        $itemId = (int) ($line->item_id ?? 0);
        $matched = $saleLines
            ->filter(fn ($saleLine) => (int) $saleLine->item_id === $itemId && ((float) $saleLine->qty_sold - (float) $saleLine->qty_shipped) > 0.0001)
            ->values();

        if ($matched->count() !== 1) {
            throw new RuntimeException("Cannot infer sale_line_id for item_id {$itemId}; please repair manually.");
        }

        DB::table('internal_usage_lines')->where('id', $line->id)->update([
            'sale_line_id' => $matched[0]->id,
            'updated_at' => now(),
        ]);

        return $matched[0];
    }
}
