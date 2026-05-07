<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facility_schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('facility_schemes', 'requires_reference_no')) {
                $table->boolean('requires_reference_no')->default(false)->after('requires_reporting');
            }
        });

        $lineTables = ['purchase_order_lines', 'receiving_entry_lines'];
        foreach ($lineTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'facility_reference_no')) $table->string('facility_reference_no')->nullable();
                if (! Schema::hasColumn($tableName, 'facility_reference_date')) $table->date('facility_reference_date')->nullable();
                if (! Schema::hasColumn($tableName, 'facility_reference_note')) $table->text('facility_reference_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        // non destructive rollback
    }
};
