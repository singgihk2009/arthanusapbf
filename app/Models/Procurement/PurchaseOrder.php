<?php

namespace App\Models\Procurement;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public const STATUSES = ['draft', 'approved', 'partially_received', 'fully_received', 'closed', 'cancelled'];

    protected $casts = [
        'po_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function items(){ return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id'); }
    public function purchaseOrderItems(){ return $this->items(); }
    public function goodsReceipts(){ return $this->hasMany(GoodsReceipt::class); }
    public function vendor(){ return $this->belongsTo(Vendor::class); }
    public function createdBy(){ return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(){ return $this->belongsTo(User::class, 'approved_by'); }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum(\DB::raw('qty_ordered * unit_price'));
        $discount = $this->items()->sum('discount_amount');
        $tax = $this->items()->sum('tax_amount');
        $grand = $this->items()->sum('line_total');

        $this->update([
            'subtotal' => $subtotal,
            'discount_total' => $discount,
            'tax_total' => $tax,
            'grand_total' => $grand,
        ]);
    }

    public function approve(?int $userId = null): void
    {
        if (!$this->isEditable()) abort(422, 'Hanya PO draft yang dapat di-approve.');
        $this->update(['status' => 'approved', 'approved_by' => $userId, 'approved_at' => now()]);
    }

    public function cancel(): void
    {
        if ($this->items()->where('qty_received', '>', 0)->exists()) abort(422, 'PO tidak dapat dibatalkan karena sudah ada receiving.');
        $this->update(['status' => 'cancelled']);
    }

    public function updateReceivingStatus(): void
    {
        $total = $this->items()->count();
        $fully = $this->items()->whereColumn('qty_received', '>=', 'qty_ordered')->count();
        $has = $this->items()->where('qty_received', '>', 0)->exists();

        $status = $this->status;
        if ($total > 0 && $fully === $total) $status = 'fully_received';
        elseif ($has) $status = 'partially_received';

        if ($status !== $this->status) $this->update(['status' => $status, 'fulfillment_status' => $status]);
    }

    public function isEditable(): bool
    {
        return strtolower((string) $this->status) === 'draft';
    }
}
