<?php

return array_filter([
    'vendor' => [
        'model' => App\Models\Procurement\Vendor::class,
        'label' => 'Vendor',
        'route_prefix' => 'vendors',
        'name_column' => 'vendor_name',
    ],
    'vendor_invoice' => [
        'model' => App\Models\Procurement\VendorInvoice::class,
        'label' => 'Vendor Invoice',
        'route_prefix' => 'vendor-invoices',
        'name_column' => 'invoice_no_internal',
    ],
    'customer' => class_exists(App\Models\Sales\Customer::class) ? [
        'model' => App\Models\Sales\Customer::class,
        'label' => 'Customer',
        'route_prefix' => 'customers',
        'name_column' => 'customer_name',
    ] : null,
    'employee' => class_exists(App\Models\Employee::class) ? [
        'model' => App\Models\Employee::class,
        'label' => 'Employee',
        'route_prefix' => 'employees',
        'name_column' => 'name',
    ] : null,
    'company' => class_exists(App\Models\Company::class) ? [
        'model' => App\Models\Company::class,
        'label' => 'Company',
        'route_prefix' => 'companies',
        'name_column' => 'name',
    ] : null,
    'product' => class_exists(App\Models\Inventory\Item::class) ? [
        'model' => App\Models\Inventory\Item::class,
        'label' => 'Product',
        'route_prefix' => 'products',
        'name_column' => 'name',
    ] : null,
]);
