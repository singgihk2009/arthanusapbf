<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('sales', function (Blueprint $table): void {
if (!Schema::hasColumn('sales','expected_delivery_date')) $table->date('expected_delivery_date')->nullable()->after('document_date');
if (!Schema::hasColumn('sales','price_list_id')) $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
if (!Schema::hasColumn('sales','salesman_id')) $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
if (!Schema::hasColumn('sales','approved_by')) $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
if (!Schema::hasColumn('sales','approved_at')) $table->timestamp('approved_at')->nullable();
}); } public function down(): void {} };
