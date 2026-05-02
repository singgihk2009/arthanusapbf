<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('regulatory_products', function (Blueprint $table) {
            $add = function (string $name, callable $callback): void {
                if (! Schema::hasColumn('regulatory_products', $name)) {
                    $callback();
                }
            };

            $add('product_type', fn () => $table->string('product_type')->nullable()->default('DRUG')->after('source_id'));
            $add('license_type', fn () => $table->string('license_type')->nullable()->after('commodity_type'));
            $add('registration_date', fn () => $table->date('registration_date')->nullable()->after('license_type'));
            $add('expiry_date', fn () => $table->date('expiry_date')->nullable()->after('registration_date'));
            $add('brand', fn () => $table->string('brand')->nullable()->after('expiry_date'));
            $add('sub_category', fn () => $table->string('sub_category')->nullable()->after('brand'));
            $add('device_type', fn () => $table->string('device_type')->nullable()->after('sub_category'));
            $add('product_group', fn () => $table->string('product_group')->nullable()->after('device_type'));
            $add('model_type', fn () => $table->string('model_type')->nullable()->after('product_group'));
            $add('device_class', fn () => $table->string('device_class')->nullable()->after('model_type'));
            $add('risk_class', fn () => $table->string('risk_class')->nullable()->after('device_class'));
            $add('registrant_name', fn () => $table->string('registrant_name')->nullable()->after('risk_class'));
            $add('registrant_address', fn () => $table->text('registrant_address')->nullable()->after('registrant_name'));
            $add('manufacturer_name', fn () => $table->string('manufacturer_name')->nullable()->after('registrant_address'));
            $add('manufacturer_address', fn () => $table->text('manufacturer_address')->nullable()->after('manufacturer_name'));
            $add('manufacturer_name_2', fn () => $table->string('manufacturer_name_2')->nullable()->after('manufacturer_address'));
        });

        $indexes = [
            'product_type' => 'regulatory_products_product_type_idx',
            'nie' => 'regulatory_products_nie_idx_2',
            'license_type' => 'regulatory_products_license_type_idx',
            'expiry_date' => 'regulatory_products_expiry_date_idx',
            'risk_class' => 'regulatory_products_risk_class_idx',
            'brand' => 'regulatory_products_brand_idx',
            'product_group' => 'regulatory_products_product_group_idx',
        ];

        foreach ($indexes as $column => $name) {
            if (! $this->indexExists('regulatory_products', $name) && Schema::hasColumn('regulatory_products', $column)) {
                Schema::table('regulatory_products', fn (Blueprint $table) => $table->index($column, $name));
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return ! empty($result);
    }

    public function down(): void
    {
        // keep safe: no destructive rollback for existing shared table
    }
};
