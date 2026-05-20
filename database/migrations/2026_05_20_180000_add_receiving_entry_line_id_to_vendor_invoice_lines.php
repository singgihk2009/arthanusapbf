<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_invoice_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_invoice_lines', 'receiving_entry_line_id')) {
                $table->unsignedBigInteger('receiving_entry_line_id')->nullable()->after('receipt_line_id');
                $table->foreign('receiving_entry_line_id')->references('id')->on('receiving_entry_lines')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_invoice_lines', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_invoice_lines', 'receiving_entry_line_id')) {
                $table->dropForeign(['receiving_entry_line_id']);
                $table->dropColumn('receiving_entry_line_id');
            }
        });
    }
};
