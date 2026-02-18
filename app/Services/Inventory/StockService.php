<?php

namespace App\Services\Inventory;

use App\Events\Inventory\StockLedgerCreated;
use App\Models\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Create stock ledger mutation and emit event to sync stock balances.
     *
     * @param  array<string, mixed>  $payload
     */
    public function postMutation(array $payload): StockLedger
    {
        return DB::transaction(function () use ($payload) {
            $ledger = StockLedger::query()->create([
                'trx_type' => $payload['trx_type'],
                'trx_id' => $payload['trx_id'],
                'trx_line_id' => $payload['trx_line_id'] ?? null,
                'warehouse_id' => $payload['warehouse_id'],
                'item_id' => $payload['item_id'],
                'batch_id' => $payload['batch_id'] ?? null,
                'qty_base' => $payload['qty_base'],
                'uom_id' => $payload['uom_id'],
                'qty_input' => $payload['qty_input'],
                'unit_cost' => $payload['unit_cost'] ?? null,
                'trx_datetime' => $payload['trx_datetime'] ?? now(),
                'created_by' => $payload['created_by'] ?? null,
            ]);

            StockLedgerCreated::dispatch($ledger);

            return $ledger;
        });
    }
}
