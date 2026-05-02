<?php

namespace App\Models\Procurement;

use App\Services\VendorComplianceService;
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

    public function purchaseOrders(){return $this->hasMany(PurchaseOrder::class);} public function vendorInvoices(){return $this->hasMany(VendorInvoice::class);} public function vendorPayments(){return $this->hasMany(VendorPayment::class);} public function vendorLedgers(){return $this->hasMany(VendorLedger::class);} public function contacts(){return $this->hasMany(VendorContact::class);} public function documents(){return $this->hasMany(VendorDocument::class);} public function bankAccounts(){return $this->hasMany(VendorBankAccount::class);} public function documentRequirements(){ return VendorDocumentRequirement::query()->where('is_active', true)->where(function($q){ $q->whereNull('vendor_type')->orWhere('vendor_type', $this->vendor_type); }); }

    public function companyDirector(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'company_director'); }
    public function technicalResponsiblePerson(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'technical_responsible_person'); }
    public function primarySalesContact(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'sales_contact')->where('is_primary', true); }
    public function primaryFinanceContact(){ return $this->hasOne(VendorContact::class)->where('contact_type', 'finance_contact')->where('is_primary', true); }

    public function requiredDocumentTypes(){ return app(VendorComplianceService::class)->getRequiredDocuments($this); }
    public function missingDocuments(){ return app(VendorComplianceService::class)->getMissingDocuments($this); }
    public function expiredDocuments(){ return app(VendorComplianceService::class)->getExpiredDocuments($this); }
    public function expiringSoonDocuments(){ return app(VendorComplianceService::class)->getExpiringSoonDocuments($this); }
    public function complianceStatus(){ return app(VendorComplianceService::class)->evaluate($this)['compliance_status']; }
    public function canCreatePurchaseOrder(){ return app(VendorComplianceService::class)->canTransact($this); }

}

