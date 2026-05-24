<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_lines', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_lines', 'notes')) {
                $table->text('notes')->nullable()->after('line_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_lines', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
