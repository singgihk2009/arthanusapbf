<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payments', 'payment_number')) $table->string('payment_number')->nullable()->unique()->after('payment_no');
            if (! Schema::hasColumn('vendor_payments', 'currency')) $table->string('currency', 3)->default('IDR')->after('currency_code');
            $table->enum('status', ['DRAFT','SUBMITTED','APPROVED','PAID','POSTED','CANCELLED'])->default('DRAFT')->change();
            foreach (['total_invoice_amount','total_wht_amount','stamp_duty_amount','freight_amount','bank_charge_amount','total_additional_cost','net_vendor_payment_amount','total_cash_out_amount'] as $col) {
                if (! Schema::hasColumn('vendor_payments', $col)) $table->decimal($col, 18, 2)->default(0);
            }
            foreach (['approved_by','paid_by','posted_by','created_by','updated_by'] as $col) if (! Schema::hasColumn('vendor_payments', $col)) $table->foreignId($col)->nullable()->constrained('users')->nullOnDelete();
            foreach (['approved_at','paid_at','posted_at'] as $col) if (! Schema::hasColumn('vendor_payments', $col)) $table->timestamp($col)->nullable();
            if (! Schema::hasColumn('vendor_payments', 'notes')) $table->text('notes')->nullable();
        });

        Schema::create('vendor_payment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->restrictOnDelete();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_total_amount', 18, 2)->default(0);
            $table->decimal('invoice_outstanding_amount', 18, 2)->default(0);
            $table->decimal('payment_amount', 18, 2)->default(0);
            $table->decimal('wht_amount', 18, 2)->default(0);
            $table->decimal('net_payment_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('vendor_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_invoices', 'wht_paid_amount')) $table->decimal('wht_paid_amount', 18, 2)->default(0);
            if (! Schema::hasColumn('vendor_invoices', 'payment_status')) $table->string('payment_status', 20)->default('unpaid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_lines');
    }
};
