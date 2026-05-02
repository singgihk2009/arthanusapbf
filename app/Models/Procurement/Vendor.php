<?php
namespace App\Models\Procurement;
use Illuminate\Database\Eloquent\Model;
class Vendor extends Model { protected $guarded=[]; public function purchaseOrders(){return $this->hasMany(PurchaseOrder::class);} public function vendorInvoices(){return $this->hasMany(VendorInvoice::class);} public function vendorPayments(){return $this->hasMany(VendorPayment::class);} public function vendorLedgers(){return $this->hasMany(VendorLedger::class);} public function contacts(){return $this->hasMany(VendorContact::class);} public function bankAccounts(){return $this->hasMany(VendorBankAccount::class);} }
