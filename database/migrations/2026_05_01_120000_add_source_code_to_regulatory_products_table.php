<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('regulatory_products', 'source_code')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->string('source_code')->nullable()->after('nie');
                $table->index('source_code', 'regulatory_products_source_code_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('regulatory_products', 'source_code')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->dropIndex('regulatory_products_source_code_idx');
                $table->dropColumn('source_code');
            });
        }
    }
};
