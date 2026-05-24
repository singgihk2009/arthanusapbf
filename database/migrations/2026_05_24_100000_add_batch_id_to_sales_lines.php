<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_lines', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('item_id')->constrained('item_batches')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_lines', 'batch_id')) {
                $table->dropConstrainedForeignId('batch_id');
            }
        });
    }
};
