<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_items')) {
            return;
        }

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'facility_reference_no')) {
                $table->string('facility_reference_no')->nullable();
            }
            if (! Schema::hasColumn('purchase_order_items', 'facility_reference_date')) {
                $table->date('facility_reference_date')->nullable();
            }
            if (! Schema::hasColumn('purchase_order_items', 'facility_reference_note')) {
                $table->text('facility_reference_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        // non-destructive rollback
    }
};
