<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internal_usages', function (Blueprint $table): void {
            if (! Schema::hasColumn('internal_usages', 'source_type')) $table->string('source_type')->nullable();
            if (! Schema::hasColumn('internal_usages', 'source_id')) $table->unsignedBigInteger('source_id')->nullable();
            if (! Schema::hasColumn('internal_usages', 'source_number')) $table->string('source_number')->nullable();
            if (! Schema::hasColumn('internal_usages', 'customer_id')) $table->unsignedBigInteger('customer_id')->nullable();
            if (! Schema::hasColumn('internal_usages', 'sale_id')) $table->unsignedBigInteger('sale_id')->nullable();
            if (! Schema::hasColumn('internal_usages', 'sales_order_synced_at')) $table->dateTime('sales_order_synced_at')->nullable();
            $table->index(['source_type','source_id']);
            $table->index('sale_id');
            $table->index('customer_id');
        });

        Schema::table('internal_usage_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('internal_usage_lines', 'sale_line_id')) $table->unsignedBigInteger('sale_line_id')->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'source_line_id')) $table->unsignedBigInteger('source_line_id')->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'qty_ordered')) $table->decimal('qty_ordered', 18, 4)->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'qty_already_shipped')) $table->decimal('qty_already_shipped', 18, 4)->default(0);
            if (! Schema::hasColumn('internal_usage_lines', 'qty_remaining')) $table->decimal('qty_remaining', 18, 4)->nullable();
            $table->index('sale_line_id');
        });
    }

    public function down(): void {}
};
