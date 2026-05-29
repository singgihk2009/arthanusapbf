<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_invoices', 'freight_amount')) {
                $table->decimal('freight_amount', 20, 6)->default(0)->after('tax_total');
            }
        });

        Schema::table('customer_invoice_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_invoice_lines', 'dispatch_id')) {
                $table->unsignedBigInteger('dispatch_id')->nullable()->after('shipment_line_id');
                $table->index('dispatch_id');
            }
            if (! Schema::hasColumn('customer_invoice_lines', 'internal_usage_line_id')) {
                $table->unsignedBigInteger('internal_usage_line_id')->nullable()->after('dispatch_id');
                $table->index('internal_usage_line_id');
            }
        });

        if (! Schema::hasTable('customer_invoice_dispatches')) {
            Schema::create('customer_invoice_dispatches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->cascadeOnDelete();
                $table->unsignedBigInteger('internal_usage_id');
                $table->timestamps();

                $table->unique(['customer_invoice_id', 'internal_usage_id'], 'customer_invoice_dispatch_unique');
                $table->index('internal_usage_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_invoice_dispatches');

        Schema::table('customer_invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_invoice_lines', 'internal_usage_line_id')) {
                $table->dropIndex(['internal_usage_line_id']);
                $table->dropColumn('internal_usage_line_id');
            }
            if (Schema::hasColumn('customer_invoice_lines', 'dispatch_id')) {
                $table->dropIndex(['dispatch_id']);
                $table->dropColumn('dispatch_id');
            }
        });

        Schema::table('customer_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_invoices', 'freight_amount')) {
                $table->dropColumn('freight_amount');
            }
        });
    }
};
