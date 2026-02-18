<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('trx_type', 30);
            $table->unsignedBigInteger('trx_id');
            $table->unsignedBigInteger('trx_line_id')->nullable();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->decimal('qty_base', 20, 6);
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('qty_input', 20, 6);
            $table->decimal('unit_cost', 20, 6)->nullable();
            $table->dateTime('trx_datetime');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'item_id', 'trx_datetime'], 'stock_ledgers_warehouse_item_idx');
            $table->index(['trx_type', 'trx_id'], 'stock_ledgers_trx_idx');
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->decimal('on_hand_base', 20, 6)->default(0);
            $table->decimal('reserved_base', 20, 6)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id', 'batch_id'], 'stock_balances_unique');
        });

        Schema::create('document_charges', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->string('charge_type', 50);
            $table->string('description')->nullable();
            $table->decimal('amount', 20, 6)->default(0);
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'document_charges_document_idx');
        });

        Schema::create('document_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('line_id')->nullable();
            $table->foreignId('tax_config_id')->nullable()->constrained('tax_configs')->nullOnDelete();
            $table->decimal('tax_percent', 8, 4)->default(0);
            $table->decimal('tax_amount', 20, 6)->default(0);
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'document_taxes_document_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_taxes');
        Schema::dropIfExists('document_charges');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_ledgers');
    }
};
