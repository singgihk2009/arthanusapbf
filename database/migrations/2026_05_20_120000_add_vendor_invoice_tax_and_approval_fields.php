<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_invoices', 'tax_rate')) $table->decimal('tax_rate', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'tax_base_amount')) $table->decimal('tax_base_amount', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'wht_tax_type')) $table->string('wht_tax_type')->nullable();
            if (!Schema::hasColumn('vendor_invoices', 'wht_tax_rate')) $table->decimal('wht_tax_rate', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'wht_tax_base_amount')) $table->decimal('wht_tax_base_amount', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'wht_tax_amount')) $table->decimal('wht_tax_amount', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'net_payable_amount')) $table->decimal('net_payable_amount', 20, 6)->default(0);
            if (!Schema::hasColumn('vendor_invoices', 'notes')) $table->text('notes')->nullable();
            if (!Schema::hasColumn('vendor_invoices', 'approved_at')) $table->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn('vendor_invoices', 'approved_by')) $table->unsignedBigInteger('approved_by')->nullable();
        });
    }
    public function down(): void {}
};
