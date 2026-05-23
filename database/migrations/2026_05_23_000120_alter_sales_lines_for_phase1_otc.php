<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('sales_lines', function (Blueprint $table): void {
if (!Schema::hasColumn('sales_lines','facility_scheme_id')) $table->foreignId('facility_scheme_id')->nullable()->constrained('facility_schemes')->nullOnDelete();
if (!Schema::hasColumn('sales_lines','qty_shipped')) $table->decimal('qty_shipped',20,6)->default(0);
if (!Schema::hasColumn('sales_lines','qty_invoiced')) $table->decimal('qty_invoiced',20,6)->default(0);
if (!Schema::hasColumn('sales_lines','price_list_id')) $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
if (!Schema::hasColumn('sales_lines','price_list_line_id')) $table->foreignId('price_list_line_id')->nullable()->constrained('price_list_lines')->nullOnDelete();
if (Schema::hasColumn('sales_lines','uom_id')) {
// left as non-nullable for compatibility
}
}); } public function down(): void {} };
