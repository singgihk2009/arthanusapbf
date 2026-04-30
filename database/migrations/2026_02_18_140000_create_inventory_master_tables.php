<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('base_uom_id')->constrained('uoms')->restrictOnDelete();
            $table->string('default_barcode')->nullable();
            $table->boolean('track_expired')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('from_uom_id')->constrained('uoms')->restrictOnDelete();
            $table->foreignId('to_uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('factor', 20, 6);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['item_id', 'from_uom_id', 'to_uom_id'], 'item_uom_unique');
        });

        Schema::create('warehouse_item_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('min_stock_base', 20, 6)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id']);
        });

        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'batch_no', 'expired_date'], 'item_batch_exp_unique');
        });

        Schema::create('tax_configs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['PPN', 'PPH']);
            $table->decimal('rate_percent', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_configs');
        Schema::dropIfExists('item_batches');
        Schema::dropIfExists('warehouse_item_settings');
        Schema::dropIfExists('item_uom_conversions');
        Schema::dropIfExists('item_barcodes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('warehouses');
    }
};
