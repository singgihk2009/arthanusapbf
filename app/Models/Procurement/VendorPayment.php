<?php

namespace App\Models\Procurement;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    protected $appends = ['can_edit', 'can_submit', 'can_approve', 'can_mark_as_paid', 'can_post', 'can_cancel'];

    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function lines(): HasMany { return $this->hasMany(VendorPaymentLine::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function paidBy(): BelongsTo { return $this->belongsTo(User::class, 'paid_by'); }
    public function postedBy(): BelongsTo { return $this->belongsTo(User::class, 'posted_by'); }

    public function bankAccount(): BelongsTo { return $this->belongsTo(VendorBankAccount::class, 'bank_account_id'); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class, 'cash_account_id'); }

    public function getCanEditAttribute(): bool { return strtoupper((string) $this->status) === 'DRAFT'; }
    public function getCanSubmitAttribute(): bool { return strtoupper((string) $this->status) === 'DRAFT'; }
    public function getCanApproveAttribute(): bool { return strtoupper((string) $this->status) === 'SUBMITTED'; }
    public function getCanMarkAsPaidAttribute(): bool { return strtoupper((string) $this->status) === 'APPROVED'; }
    public function getCanPostAttribute(): bool { return strtoupper((string) $this->status) === 'PAID'; }
    public function getCanCancelAttribute(): bool { return in_array(strtoupper((string) $this->status), ['DRAFT', 'SUBMITTED', 'APPROVED', 'PAID'], true); }
}
