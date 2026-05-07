<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('purchase_order_items')) {
            return;
        }

        if (! Schema::hasColumn('purchase_order_items', 'facility_scheme_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->foreignId('facility_scheme_id')->nullable()->after('id')->constrained('facility_schemes')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('purchase_order_items', 'facility_type') && Schema::hasColumn('purchase_order_items', 'facility_scheme_id')) {
            DB::statement("\n                UPDATE purchase_order_items poi\n                LEFT JOIN facility_schemes fs ON fs.code = poi.facility_type\n                SET poi.facility_scheme_id = fs.id\n                WHERE poi.facility_scheme_id IS NULL\n                  AND poi.facility_type IS NOT NULL\n            ");
        }
    }

    public function down(): void
    {
        // non-destructive rollback
    }
};
