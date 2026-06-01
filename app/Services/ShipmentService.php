<?php

namespace App\Services;

use App\Models\Sales\Sale;
use App\Models\Sales\Shipment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShipmentService
{
    public function generateNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'SHP-'.now()->format('Ym').'-';
            $last = Shipment::withTrashed()
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('number');
            $sequence = $last ? ((int) substr($last, -5)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        });
    }

    public function createFromSale(Sale $sale, array $data): Shipment
    {
        if (! in_array($sale->status, ['approved', 'partially_shipped'], true)) {
            throw ValidationException::withMessages(['sale' => 'Sale must be approved/partially_shipped']);
        }

        return DB::transaction(function () use ($sale, $data) {
            $shipment = Shipment::create([
                'number' => $this->generateNumber(),
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $data['warehouse_id'] ?? $sale->warehouse_id,
                'shipment_date' => $data['shipment_date'],
                'status' => 'draft',
                'driver_name' => $data['driver_name'] ?? null,
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'courier_name' => $data['courier_name'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $map = $sale->lines()->get()->keyBy('id');
            foreach ($data['lines'] as $line) {
                $saleLine = $map[$line['sale_line_id']] ?? null;
                if (! $saleLine || $saleLine->sale_id !== $sale->id) {
                    throw ValidationException::withMessages(['lines' => 'Invalid sale line']);
                }

                $remaining = (float) $saleLine->qty_sold - (float) $saleLine->qty_shipped;
                if ((float) $line['qty_shipped'] > $remaining + 0.0000001) {
                    throw ValidationException::withMessages(['lines' => 'Qty shipped exceeds remaining']);
                }

                $shipment->lines()->create([
                    'sale_line_id' => $saleLine->id,
                    'item_id' => $line['item_id'],
                    'batch_id' => $line['batch_id'] ?? null,
                    'facility_scheme_id' => $line['facility_scheme_id'] ?? null,
                    'uom_id' => $line['uom_id'] ?? $saleLine->uom_id,
                    'qty_ordered' => $saleLine->qty_sold,
                    'qty_already_shipped' => $saleLine->qty_shipped,
                    'qty_shipped' => $line['qty_shipped'],
                    'qty_base' => $line['qty_base'] ?? $saleLine->qty_base,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $shipment->fresh('lines');
        });
    }

    public function postShipment(Shipment $shipment): Shipment
    {
        return DB::transaction(function () use ($shipment) {
            $shipment = $shipment->fresh(['customer', 'sale.lines', 'lines']);
            if ($shipment->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Only draft can be posted']);
            }

            $sale = $shipment->sale;
            $warehouseId = $shipment->warehouse_id ?: $sale?->warehouse_id;
            if (! $warehouseId) {
                throw ValidationException::withMessages(['warehouse_id' => 'Missing warehouse']);
            }

            $usageId = DB::table('internal_usages')->insertGetId([
                'number' => 'DSP-'.$shipment->number,
                'warehouse_id' => $warehouseId,
                'facility_scheme_id' => null,
                'transaction_code' => 'SALES_SHIPMENT',
                'outbound_number' => $shipment->number,
                'sender_receiver_name' => $shipment->customer?->customer_name,
                'department' => 'SALES',
                'cost_center' => 'SALES',
                'document_date' => $shipment->shipment_date,
                'status' => 'DRAFT',
                'source_type' => 'sales_order',
                'source_id' => $sale?->id,
                'source_number' => $sale?->number,
                'customer_id' => $shipment->customer_id,
                'sale_id' => $sale?->id,
                'notes' => 'Generated from Sales Shipment '.$shipment->number,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($shipment->lines as $line) {
                $saleLine = $sale->lines->firstWhere('id', $line->sale_line_id);
                $remaining = (float) $saleLine->qty_sold - (float) $saleLine->qty_shipped;
                if ((float) $line->qty_shipped <= 0 || (float) $line->qty_shipped > $remaining + 0.0000001) {
                    throw ValidationException::withMessages(['lines' => 'Invalid shipment qty']);
                }

                DB::table('internal_usage_lines')->insert([
                    'internal_usage_id' => $usageId,
                    'item_id' => $line->item_id,
                    'batch_id' => $line->batch_id,
                    'qty_used' => $line->qty_shipped,
                    'uom_id' => $line->uom_id,
                    'qty_base' => $line->qty_base ?? $line->qty_shipped,
                    'facility_scheme_id' => $line->facility_scheme_id,
                    'sale_line_id' => $line->sale_line_id,
                    'source_line_id' => $line->sale_line_id,
                    'notes' => $line->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            app(\App\Http\Controllers\Apps\InventoryPostingController::class)->postInternalUsage(request(), $usageId);

            $shipment->update([
                'dispatch_id' => $usageId,
                'status' => 'posted',
                'posted_by' => Auth::id(),
                'posted_at' => now(),
            ]);

            return $shipment->fresh(['sale', 'lines']);
        });
    }

    public function cancelShipment(Shipment $shipment, string $reason): Shipment
    {
        if ($shipment->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft']);
        }

        $shipment->update([
            'status' => 'cancelled',
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $shipment;
    }
}
