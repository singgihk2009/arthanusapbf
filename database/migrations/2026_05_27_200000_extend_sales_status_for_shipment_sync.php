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

        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','CANCELLED','draft','submitted','approved','cancelled','partially_shipped','fully_shipped','fully_invoiced','closed') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'status')) {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY COLUMN status ENUM('DRAFT','SUBMITTED','APPROVED','POSTED','CANCELLED','draft','submitted','approved','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
