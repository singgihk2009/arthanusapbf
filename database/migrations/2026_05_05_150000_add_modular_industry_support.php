<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('company_modules')) {
            Schema::create('company_modules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id');
                $table->string('module_code');
                $table->boolean('is_enabled')->default(false);
                $table->json('settings_json')->nullable();
                $table->timestamps();
                $table->unique(['company_id', 'module_code']);
                $table->index('company_id');
            });
        }

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'product_type')) $table->string('product_type', 30)->nullable()->after('name');
            if (!Schema::hasColumn('items', 'regulatory_category')) $table->string('regulatory_category', 30)->nullable()->after('product_type');
            if (!Schema::hasColumn('items', 'regulatory_source_id')) $table->unsignedBigInteger('regulatory_source_id')->nullable()->after('nie');
            if (!Schema::hasColumn('items', 'regulatory_product_id')) $table->unsignedBigInteger('regulatory_product_id')->nullable()->after('regulatory_source_id');
            if (!Schema::hasColumn('items', 'is_batch_tracked')) $table->boolean('is_batch_tracked')->default(false)->after('requires_batch_tracking');
            if (!Schema::hasColumn('items', 'is_expiry_tracked')) $table->boolean('is_expiry_tracked')->default(false)->after('requires_expiry_tracking');
            if (!Schema::hasColumn('items', 'requires_fefo')) $table->boolean('requires_fefo')->default(false)->after('is_expiry_tracked');
        });

        foreach (['purchase_order_items', 'goods_receipt_items', 'stock_movements'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if (!Schema::hasColumn($table->getTable(), 'is_facility_item')) $table->boolean('is_facility_item')->default(false);
                if (!Schema::hasColumn($table->getTable(), 'facility_type')) $table->string('facility_type', 40)->nullable();
                if (!Schema::hasColumn($table->getTable(), 'facility_document_id')) $table->unsignedBigInteger('facility_document_id')->nullable();
                if (!Schema::hasColumn($table->getTable(), 'facility_reference_no')) $table->string('facility_reference_no')->nullable();
                if (!Schema::hasColumn($table->getTable(), 'kek_classification')) $table->string('kek_classification', 40)->nullable();
            });
        }

        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'facility_status')) $table->string('facility_status', 30)->nullable();
            if (!Schema::hasColumn('stock_movements', 'facility_notes')) $table->text('facility_notes')->nullable();
            $table->index('product_id');
            $table->index('movement_date');
            $table->index('is_facility_item');
            $table->index('facility_type');
            $table->index('kek_classification');
            $table->index('facility_status');
            $table->index('facility_document_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'purpose_type')) $table->string('purpose_type', 60)->nullable()->after('title');
            if (!Schema::hasColumn('documents', 'linked_module_code')) $table->string('linked_module_code', 60)->nullable()->after('purpose_type');
            $table->index('purpose_type');
        });
    }

    public function down(): void
    {
        // keep backward compatibility, non-destructive rollback intentionally omitted
    }
};
