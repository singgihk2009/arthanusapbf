<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'manufacturer_name')) {
                $table->string('manufacturer_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('items', 'composition_text')) {
                $table->text('composition_text')->nullable()->after('manufacturer_name');
            }
            if (! Schema::hasColumn('items', 'packing_text')) {
                $table->text('packing_text')->nullable()->after('composition_text');
            }
            if (! Schema::hasColumn('items', 'regulatory_class')) {
                $table->string('regulatory_class')->nullable()->after('packing_text');
            }
        });

        Schema::table('item_regulatory_products', function (Blueprint $table) {
            if (! Schema::hasColumn('item_regulatory_products', 'source_name')) {
                $table->string('source_name')->nullable()->after('is_primary');
            }
            if (! Schema::hasColumn('item_regulatory_products', 'source_code')) {
                $table->string('source_code')->nullable()->after('source_name');
            }
        });

        Schema::table('regulatory_products', function (Blueprint $table) {
            if (! Schema::hasColumn('regulatory_products', 'source_code')) {
                $table->string('source_code')->nullable()->after('nie');
                $table->index('source_code', 'regulatory_products_source_code_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('regulatory_products', function (Blueprint $table) {
            if (Schema::hasColumn('regulatory_products', 'source_code')) {
                $table->dropIndex('regulatory_products_source_code_idx');
                $table->dropColumn('source_code');
            }
        });

        Schema::table('item_regulatory_products', function (Blueprint $table) {
            if (Schema::hasColumn('item_regulatory_products', 'source_name')) {
                $table->dropColumn('source_name');
            }
            if (Schema::hasColumn('item_regulatory_products', 'source_code')) {
                $table->dropColumn('source_code');
            }
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['manufacturer_name', 'composition_text', 'packing_text', 'regulatory_class']);
        });
    }
};
