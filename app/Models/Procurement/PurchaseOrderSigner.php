<?php

namespace App\Models\Procurement;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderSigner extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function requesterEmployee() { return $this->belongsTo(Employee::class, 'requester_employee_id'); }
    public function approverEmployee() { return $this->belongsTo(Employee::class, 'approver_employee_id'); }
}
