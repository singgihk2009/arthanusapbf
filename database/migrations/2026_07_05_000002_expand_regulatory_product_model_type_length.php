<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('regulatory_products') || ! Schema::hasColumn('regulatory_products', 'model_type')) {
            return;
        }

        DB::statement('ALTER TABLE regulatory_products MODIFY model_type TEXT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('regulatory_products') || ! Schema::hasColumn('regulatory_products', 'model_type')) {
            return;
        }

        DB::statement('ALTER TABLE regulatory_products MODIFY model_type VARCHAR(255) NULL');
    }
};
