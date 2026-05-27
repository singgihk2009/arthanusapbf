<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'status')) {
            return;
        }

        // Normalize legacy uppercase statuses before changing enum.
        DB::table('sales')->where('status', 'DRAFT')->update(['status' => 'draft']);
        DB::table('sales')->where('status', 'SUBMITTED')->update(['status' => 'submitted']);
        DB::table('sales')->where('status', 'APPROVED')->update(['status' => 'approved']);
        DB::table('sales')->where('status', 'POSTED')->update(['status' => 'posted']);
        DB::table('sales')->where('status', 'CANCELLED')->update(['status' => 'cancelled']);

        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('draft','submitted','approved','posted','cancelled','partially_shipped','fully_shipped','fully_invoiced','closed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'status')) {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('draft','submitted','approved','posted','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
