<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();$table->string('customer_code')->unique();$table->string('customer_name');$table->string('customer_type')->nullable();$table->string('contact_person')->nullable();$table->string('phone')->nullable();$table->string('email')->nullable();$table->text('address')->nullable();$table->string('city')->nullable();$table->string('npwp')->nullable();$table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();$table->unsignedInteger('payment_term_days')->default(0);$table->decimal('credit_limit',18,2)->default(0);$table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();$table->enum('status',['active','inactive'])->default('active');$table->text('notes')->nullable();$table->timestamps();$table->softDeletes();
            });
        }
        if (!Schema::hasTable('price_lists')) {
            Schema::create('price_lists', function (Blueprint $table): void {
                $table->id();$table->string('code')->unique();$table->string('name');$table->text('description')->nullable();$table->string('customer_group')->nullable();$table->date('effective_from')->nullable();$table->date('effective_to')->nullable();$table->boolean('is_default')->default(false);$table->enum('status',['active','inactive'])->default('active');$table->timestamps();$table->softDeletes();
            });
        }
        if (!Schema::hasTable('price_list_lines')) {
            Schema::create('price_list_lines', function (Blueprint $table): void {
                $table->id();$table->foreignId('price_list_id')->constrained('price_lists')->cascadeOnDelete();$table->foreignId('item_id')->constrained('items')->restrictOnDelete();$table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();$table->decimal('min_qty',20,6)->default(1);$table->decimal('price',18,2);$table->decimal('discount_percent',8,2)->default(0);$table->boolean('tax_included')->default(false);$table->enum('status',['active','inactive'])->default('active');$table->timestamps();
            });
        }
        if (!Schema::hasTable('shipments')) {
            Schema::create('shipments', function (Blueprint $table): void {
                $table->id();$table->string('number')->unique();$table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();$table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();$table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();$table->date('shipment_date');$table->enum('status',['draft','picked','packed','shipped','delivered','cancelled'])->default('draft');$table->text('notes')->nullable();$table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('posted_at')->nullable();$table->timestamps();$table->softDeletes();
            });
        }
        if (!Schema::hasTable('shipment_lines')) {
            Schema::create('shipment_lines', function (Blueprint $table): void {
                $table->id();$table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();$table->foreignId('sale_line_id')->constrained('sales_lines')->restrictOnDelete();$table->foreignId('item_id')->constrained('items')->restrictOnDelete();$table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();$table->foreignId('facility_scheme_id')->nullable()->constrained('facility_schemes')->nullOnDelete();$table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();$table->decimal('qty_shipped',20,6);$table->decimal('qty_base',20,6)->nullable();$table->timestamps();
            });
        }
        if (!Schema::hasTable('customer_invoices')) {
            Schema::create('customer_invoices', function (Blueprint $table): void {
                $table->id();$table->string('number')->unique();$table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();$table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();$table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();$table->date('invoice_date');$table->date('due_date')->nullable();$table->enum('status',['draft','posted','partially_paid','paid','overdue','cancelled'])->default('draft');$table->decimal('subtotal',20,6)->default(0);$table->decimal('discount_total',20,6)->default(0);$table->decimal('tax_total',20,6)->default(0);$table->decimal('grand_total',20,6)->default(0);$table->decimal('amount_paid',20,6)->default(0);$table->decimal('balance_due',20,6)->default(0);$table->text('notes')->nullable();$table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('posted_at')->nullable();$table->timestamps();$table->softDeletes();
            });
        }
        if (!Schema::hasTable('customer_invoice_lines')) {
            Schema::create('customer_invoice_lines', function (Blueprint $table): void {
                $table->id();$table->foreignId('customer_invoice_id')->constrained('customer_invoices')->cascadeOnDelete();$table->foreignId('shipment_line_id')->nullable()->constrained('shipment_lines')->nullOnDelete();$table->foreignId('sale_line_id')->nullable()->constrained('sales_lines')->nullOnDelete();$table->foreignId('item_id')->constrained('items')->restrictOnDelete();$table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();$table->decimal('qty',20,6);$table->decimal('unit_price',18,2);$table->decimal('discount_percent',8,2)->default(0);$table->decimal('discount_amount',18,2)->default(0);$table->decimal('tax_percent',8,2)->default(0);$table->decimal('tax_amount',18,2)->default(0);$table->decimal('line_total',18,2);$table->timestamps();
            });
        }
        if (!Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table): void {
                $table->id();$table->string('number')->unique();$table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();$table->date('payment_date');$table->string('payment_method')->nullable();$table->unsignedBigInteger('bank_account_id')->nullable();$table->decimal('amount',18,2);$table->decimal('bank_charge',18,2)->default(0);$table->decimal('discount_taken',18,2)->default(0);$table->enum('status',['draft','posted','cancelled'])->default('draft');$table->text('notes')->nullable();$table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();$table->timestamp('posted_at')->nullable();$table->timestamps();$table->softDeletes();
            });
        }
        if (!Schema::hasTable('customer_payment_allocations')) {
            Schema::create('customer_payment_allocations', function (Blueprint $table): void {
                $table->id();$table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();$table->foreignId('customer_invoice_id')->constrained('customer_invoices')->restrictOnDelete();$table->decimal('amount_applied',18,2);$table->decimal('discount_taken',18,2)->default(0);$table->decimal('writeoff_amount',18,2)->default(0);$table->timestamps();
            });
        }
    }
    public function down(): void {}
};
