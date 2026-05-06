<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('receiving_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('receiving_entries', 'source_type')) {
                $table->string('source_type')->nullable()->after('transaction_code');
            }
            if (! Schema::hasColumn('receiving_entries', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('receiving_entries', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('vendor_name');
            }
        });

        Schema::table('receiving_entry_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('receiving_entry_lines', 'source_item_id')) {
                $table->unsignedBigInteger('source_item_id')->nullable()->after('receiving_entry_id');
            }
            if (! Schema::hasColumn('receiving_entry_lines', 'previously_received_qty')) {
                $table->decimal('previously_received_qty', 20, 6)->default(0)->after('qty');
            }
            if (! Schema::hasColumn('receiving_entry_lines', 'remaining_qty')) {
                $table->decimal('remaining_qty', 20, 6)->nullable()->after('previously_received_qty');
            }
            if (! Schema::hasColumn('receiving_entry_lines', 'inventory_unit_cost')) {
                $table->decimal('inventory_unit_cost', 20, 6)->nullable()->after('price');
            }
            if (! Schema::hasColumn('receiving_entry_lines', 'inventory_total_cost')) {
                $table->decimal('inventory_total_cost', 20, 6)->nullable()->after('inventory_unit_cost');
            }
        });
    }

    public function down(): void
    {
    }
};
