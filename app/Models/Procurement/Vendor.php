<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_pkp' => 'boolean',
        'qualification_date' => 'date',
        'verified_at' => 'datetime',
        'cdakb_cpakb_certificate_expiry_date' => 'date',
    ];

    public function purchaseOrders(){return $this->hasMany(PurchaseOrder::class);} public function vendorInvoices(){return $this->hasMany(VendorInvoice::class);} public function vendorPayments(){return $this->hasMany(VendorPayment::class);} public function vendorLedgers(){return $this->hasMany(VendorLedger::class);} public function contacts(){return $this->hasMany(VendorContact::class);} public function documents(){return $this->hasMany(VendorDocument::class);} public function bankAccounts(){return $this->hasMany(VendorBankAccount::class);} 

    public function companyDirector(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'company_director'); }
    public function technicalResponsiblePerson(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'technical_responsible_person'); }
    public function primarySalesContact(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'sales_contact')->where('is_primary', true); }
    public function primaryFinanceContact(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'finance_contact')->where('is_primary', true); }
}
