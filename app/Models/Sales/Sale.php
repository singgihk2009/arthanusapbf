<?php

namespace App\Models\Sales;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number','customer_id','warehouse_id','document_date','expected_delivery_date','price_list_id','status',
        'subtotal','discount_total','tax_total','grand_total','notes','submitted_by','submitted_at','approved_by',
        'approved_at','cancelled_by','cancelled_at','cancel_reason','salesman_id','credit_status','credit_checked_at',
    ];

    protected $casts = [
        'document_date' => 'date','expected_delivery_date' => 'date','subtotal' => 'decimal:2','discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2','grand_total' => 'decimal:2','submitted_at' => 'datetime','approved_at' => 'datetime',
        'cancelled_at' => 'datetime','credit_checked_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'can_edit', 'can_submit', 'can_approve', 'can_cancel', 'can_create_shipment'];

    public function customer(){ return $this->belongsTo(Customer::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function priceList(){ return $this->belongsTo(PriceList::class); }
    public function lines(){ return $this->hasMany(SalesLine::class); }
    public function shipments(){ return $this->hasMany(Shipment::class); }
    public function invoices(){ return $this->hasMany(CustomerInvoice::class); }

    protected function statusLabel(): Attribute { return Attribute::get(fn() => str($this->status)->replace('_', ' ')->title()->toString()); }
    protected function canEdit(): Attribute { return Attribute::get(fn() => $this->status === 'draft'); }
    protected function canSubmit(): Attribute { return Attribute::get(fn() => $this->status === 'draft'); }
    protected function canApprove(): Attribute { return Attribute::get(fn() => $this->status === 'submitted'); }
    protected function canCancel(): Attribute { return Attribute::get(fn() => !in_array($this->status, ['fully_shipped','fully_invoiced','closed','cancelled'], true)); }
    protected function canCreateShipment(): Attribute { return Attribute::get(fn() => in_array($this->status, ['approved','partially_shipped'], true)); }
}
