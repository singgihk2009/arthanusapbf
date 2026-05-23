<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code','customer_code','customer_name','customer_type','contact_person','phone','email','address','city','province','postal_code','country','npwp',
        'price_list_id','payment_term_days','credit_limit','salesman_id','status','notes',
    ];

    protected $casts = [
        'payment_term_days' => 'integer',
        'credit_limit' => 'decimal:2',
    ];

    protected $appends = ['display_name'];

    public function priceList() { return class_exists(PriceList::class) ? $this->belongsTo(PriceList::class) : null; }
    public function salesman() { return class_exists(\App\Models\Salesman::class) ? $this->belongsTo(\App\Models\Salesman::class) : null; }
    public function salesOrders() { return class_exists(Sale::class) ? $this->hasMany(Sale::class) : null; }
    public function invoices() { return class_exists(CustomerInvoice::class) ? $this->hasMany(CustomerInvoice::class) : null; }
    public function payments() { return class_exists(CustomerPayment::class) ? $this->hasMany(CustomerPayment::class) : null; }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->customer_code.' - '.$this->customer_name);
    }
}
