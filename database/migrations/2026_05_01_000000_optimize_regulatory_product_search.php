<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('regulatory_products', function (Blueprint $table) {
            $table->index('source_id', 'regulatory_products_source_id_idx');
            $table->index('commodity_type', 'regulatory_products_commodity_type_idx');
            $table->index('dosage_form', 'regulatory_products_dosage_form_idx');
            $table->index('industry_name', 'regulatory_products_industry_name_idx');
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE regulatory_products ADD FULLTEXT fulltext_regulatory_product_search (product_name_source, industry_name, raw_composition_text)');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE regulatory_products DROP INDEX fulltext_regulatory_product_search');
        }

        Schema::table('regulatory_products', function (Blueprint $table) {
            $table->dropIndex('regulatory_products_source_id_idx');
            $table->dropIndex('regulatory_products_commodity_type_idx');
            $table->dropIndex('regulatory_products_dosage_form_idx');
            $table->dropIndex('regulatory_products_industry_name_idx');
        });
    }
};
