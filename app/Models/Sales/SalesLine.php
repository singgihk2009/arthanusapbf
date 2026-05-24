<?php

namespace App\Models\Sales;

use App\Models\Inventory\FacilityScheme;
use App\Models\Inventory\Item;
use App\Models\Inventory\Uom;
use Illuminate\Database\Eloquent\Model;

class SalesLine extends Model
{
    protected $table = 'sales_lines';

    protected $fillable = [
        'sale_id','item_id','batch_id','uom_id','facility_scheme_id','qty_sold','qty_base','qty_shipped','qty_invoiced','unit_price',
        'discount_percent','discount_amount','tax_percent','tax_amount','line_total','price_list_id','price_list_line_id','notes',
    ];

    protected $casts = [
        'qty_sold' => 'decimal:4','qty_base' => 'decimal:4','qty_shipped' => 'decimal:4','qty_invoiced' => 'decimal:4',
        'unit_price' => 'decimal:2','discount_percent' => 'decimal:2','discount_amount' => 'decimal:2','tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2','line_total' => 'decimal:2',
    ];

    public function sale(){ return $this->belongsTo(Sale::class); }
    public function item(){ return $this->belongsTo(Item::class); }
    public function batch(){ return $this->belongsTo(\App\Models\Inventory\ItemBatch::class); }
    public function uom(){ return $this->belongsTo(Uom::class); }
    public function facilityScheme(){ return $this->belongsTo(FacilityScheme::class); }
    public function priceList(){ return $this->belongsTo(PriceList::class); }
    public function priceListLine(){ return $this->belongsTo(PriceListLine::class); }
    public function shipmentLines(){ return $this->hasMany(ShipmentLine::class,'sale_line_id'); }
    public function invoiceLines(){ return $this->hasMany(CustomerInvoiceLine::class,'sale_line_id'); }
}
