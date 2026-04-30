<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inv_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('trx_no');
            $table->string('trx_type', 30);
            $table->date('trx_date');
            $table->enum('status', ['draft', 'final', 'void', 'reversed'])->default('draft');
            $table->enum('gl_status', ['draft', 'pending', 'sent', 'posted', 'error'])->default('draft');
            $table->string('gl_reference_no')->nullable();
            $table->timestamp('gl_posted_at')->nullable();
            $table->text('gl_error_message')->nullable();
            $table->enum('valuation_method', ['AVG', 'BATCH', 'MIXED'])->nullable();
            $table->decimal('total_qty', 20, 6)->default(0);
            $table->decimal('total_amount', 20, 6)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->char('source_hash', 64)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('inv_transactions')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('inv_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_table', 'source_id'], 'inv_transactions_source_unique');
            $table->index(['status', 'gl_status', 'trx_date'], 'inv_transactions_status_gl_date_idx');
        });

        Schema::create('inv_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inv_transaction_id')->constrained('inv_transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('qty', 20, 6);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches')->nullOnDelete();
            $table->string('batch_no')->nullable();
            $table->date('expired_date')->nullable();
            $table->enum('valuation_method', ['AVG', 'BATCH']);
            $table->decimal('unit_cost_snapshot', 20, 6);
            $table->decimal('amount_snapshot', 20, 6);
            $table->enum('cost_source', ['AVG_RATE', 'BATCH_LAYER', 'MANUAL_ADJ'])->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('inv_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('on_hand_qty', 20, 6)->default(0);
            $table->decimal('avg_cost', 20, 6)->default(0);
            $table->decimal('stock_value', 20, 6)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id', 'product_id'], 'inv_balances_unique');
        });

        Schema::create('inv_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('items')->cascadeOnDelete();
            $table->string('batch_no');
            $table->date('expired_date')->nullable();
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('qty_on_hand', 20, 6)->default(0);
            $table->decimal('stock_value', 20, 6)->default(0);
            $table->enum('status', ['active', 'depleted', 'expired'])->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'warehouse_id', 'product_id', 'batch_no'], 'inv_batches_unique');
        });

        Schema::create('integration_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 50);
            $table->string('aggregate_type', 50);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('idempotency_key')->unique();
            $table->json('payload_json');
            $table->char('payload_hash', 64);
            $table->enum('status', ['ready', 'processing', 'sent', 'acked', 'failed'])->default('ready');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at'], 'integration_outbox_status_available_idx');
        });

        Schema::create('integration_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->string('source_app', 30);
            $table->string('message_type', 30);
            $table->json('payload_json');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_period_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1)->unique();
            $table->date('lock_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_period_locks');
        Schema::dropIfExists('integration_inbox');
        Schema::dropIfExists('integration_outbox');
        Schema::dropIfExists('inv_batches');
        Schema::dropIfExists('inv_balances');
        Schema::dropIfExists('inv_transaction_items');
        Schema::dropIfExists('inv_transactions');
    }
};
