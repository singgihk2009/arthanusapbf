<?php

namespace App\Events\Inventory;

use App\Models\Inventory\StockLedger;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockLedgerCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public StockLedger $stockLedger)
    {
    }
}
