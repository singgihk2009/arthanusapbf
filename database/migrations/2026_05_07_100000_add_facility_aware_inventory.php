<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facility_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_restricted')->default(false);
            $table->boolean('requires_tracking')->default(true);
            $table->boolean('requires_reporting')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->string('tax_treatment')->nullable();
            $table->string('ownership_type')->nullable();
            $table->json('allowed_movement_types')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_facility_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses', indexName: 'wfs_wh_fk')->cascadeOnDelete();
            $table->foreignId('facility_scheme_id')->constrained('facility_schemes', indexName: 'wfs_fac_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['warehouse_id', 'facility_scheme_id'], 'wfs_wh_fac_unq');
        });

        Schema::create('customer_facility_schemes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers', indexName: 'cfs_cust_fk')->cascadeOnDelete();
            $table->foreignId('facility_scheme_id')->constrained('facility_schemes', indexName: 'cfs_fac_fk')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['customer_id', 'facility_scheme_id'], 'cfs_cust_fac_unq');
        });

        $tables = [
            'purchase_requisition_lines', 'purchase_order_lines', 'receiving_entry_lines', 'item_batches',
            'stock_ledgers', 'stock_balances', 'sales_lines', 'internal_usage_lines', 'warehouse_transfer_lines',
            'stock_adjustment_lines', 'stock_opname_lines', 'goods_receipt_lines',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'facility_scheme_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('facility_scheme_id')->nullable()->after('id')->constrained('facility_schemes')->nullOnDelete();
            });
        }

        DB::table('facility_schemes')->insert([
            ['code' => 'REGULAR', 'name' => 'Regular', 'description' => 'Default unrestricted facility.', 'is_restricted' => false, 'requires_tracking' => true, 'requires_reporting' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'BPJS', 'name' => 'BPJS Program', 'description' => 'BPJS restricted stock.', 'is_restricted' => true, 'requires_tracking' => true, 'requires_reporting' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'KEK_VAT_EXEMPT', 'name' => 'KEK VAT Exempt', 'description' => 'KEK bebas PPN.', 'is_restricted' => true, 'requires_tracking' => true, 'requires_reporting' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'HIBAH', 'name' => 'Hibah', 'description' => 'Grant stock.', 'is_restricted' => true, 'requires_tracking' => true, 'requires_reporting' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CONSIGNMENT', 'name' => 'Consignment', 'description' => 'Consignment stock.', 'is_restricted' => true, 'requires_tracking' => true, 'requires_reporting' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $regularId = DB::table('facility_schemes')->where('code', 'REGULAR')->value('id');
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'facility_scheme_id')) {
                DB::table($tableName)->whereNull('facility_scheme_id')->update(['facility_scheme_id' => $regularId]);
            }
        }
    }

    public function down(): void
    {
        $tables = ['goods_receipt_lines','stock_opname_lines','stock_adjustment_lines','warehouse_transfer_lines','internal_usage_lines','sales_lines','stock_balances','stock_ledgers','item_batches','receiving_entry_lines','purchase_order_lines','purchase_requisition_lines'];
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'facility_scheme_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('facility_scheme_id');
                });
            }
        }
        Schema::dropIfExists('customer_facility_schemes');
        Schema::dropIfExists('warehouse_facility_schemes');
        Schema::dropIfExists('facility_schemes');
    }
};
