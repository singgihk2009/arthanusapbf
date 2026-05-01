<?php

namespace App\Services\Regulatory;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RegulatorySalesReportQuery
{
    public function bySource(string $sourceName): Builder
    {
        return DB::table('sales_invoice_items as sii')
            ->join('items', 'items.id', '=', 'sii.item_id')
            ->join('item_regulatory_products as irp', 'irp.item_id', '=', 'items.id')
            ->join('regulatory_products as rp', 'rp.id', '=', 'irp.regulatory_product_id')
            ->join('regulatory_sources as rs', 'rs.id', '=', 'rp.source_id')
            ->where('rs.source_name', strtoupper($sourceName))
            ->select([
                'sii.id as sales_invoice_item_id',
                'sii.item_id',
                'items.sku',
                'items.name as item_name',
                'rs.source_name',
                DB::raw('COALESCE(irp.source_code, rp.source_code, rp.nie) as regulatory_code'),
                'rp.product_name_source as regulatory_product_name',
                'irp.is_primary',
            ]);
    }

    public function bpom(): Builder
    {
        return $this->bySource('BPOM');
    }

    public function kemenkes(): Builder
    {
        return $this->bySource('KEMENKES');
    }
}
