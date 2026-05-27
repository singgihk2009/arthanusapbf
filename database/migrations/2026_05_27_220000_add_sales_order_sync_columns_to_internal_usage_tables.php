<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (! Schema::hasColumn('internal_usages', 'sales_order_synced_at')) $table->timestamp('sales_order_synced_at')->nullable();
            if (! Schema::hasColumn('internal_usages', 'sales_order_synced_by')) $table->unsignedBigInteger('sales_order_synced_by')->nullable();
        });

        $this->addIndexIfMissing('internal_usages', 'internal_usages_source_type_source_id_idx', 'source_type, source_id');
        $this->addIndexIfMissing('internal_usages', 'internal_usages_sale_id_idx', 'sale_id');
        $this->addIndexIfMissing('internal_usages', 'internal_usages_customer_id_idx', 'customer_id');
        $this->addIndexIfMissing('internal_usages', 'internal_usages_sales_order_synced_at_idx', 'sales_order_synced_at');

        Schema::table('internal_usage_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('internal_usage_lines', 'sale_line_id')) $table->unsignedBigInteger('sale_line_id')->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'source_line_id')) $table->unsignedBigInteger('source_line_id')->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'qty_ordered')) $table->decimal('qty_ordered', 18, 4)->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'qty_already_shipped')) $table->decimal('qty_already_shipped', 18, 4)->nullable();
            if (! Schema::hasColumn('internal_usage_lines', 'qty_remaining')) $table->decimal('qty_remaining', 18, 4)->nullable();
        });

        $this->addIndexIfMissing('internal_usage_lines', 'internal_usage_lines_sale_line_id_idx', 'sale_line_id');
        $this->addIndexIfMissing('internal_usage_lines', 'internal_usage_lines_source_line_id_idx', 'source_line_id');
    }

    private function addIndexIfMissing(string $table, string $indexName, string $columnsSql): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        if (! $exists) {
            DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnsSql})");
        }
    }
};
