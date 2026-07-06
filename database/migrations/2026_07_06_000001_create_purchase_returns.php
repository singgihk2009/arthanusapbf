<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->date('return_date');
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['DRAFT', 'SUBMITTED', 'APPROVED', 'POSTED', 'CANCELLED', 'VOID'])->default('DRAFT');
            $table->enum('reason_category', ['DAMAGED', 'EXPIRED', 'NEAR_EXPIRED', 'WRONG_ITEM', 'WRONG_BATCH', 'WRONG_QTY', 'QUALITY_REJECTED', 'RECALL', 'OTHER'])->default('DAMAGED');
            $table->decimal('total_qty', 20, 6)->default(0);
            $table->decimal('total_amount', 20, 6)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendor_id', 'return_date']);
            $table->index(['status', 'return_date']);
        });

        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->constrained('goods_receipt_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('qty_returned', 20, 6);
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('line_amount', 20, 6)->default(0);
            $table->enum('reason', ['DAMAGED', 'EXPIRED', 'NEAR_EXPIRED', 'WRONG_ITEM', 'WRONG_BATCH', 'WRONG_QTY', 'QUALITY_REJECTED', 'RECALL', 'OTHER'])->default('DAMAGED');
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_invoice_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('vendor_invoice_id')->nullable()->constrained('vendor_invoices')->nullOnDelete();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->string('deduction_no')->unique();
            $table->date('deduction_date');
            $table->decimal('amount', 20, 6)->default(0);
            $table->decimal('applied_amount', 20, 6)->default(0);
            $table->decimal('remaining_amount', 20, 6)->default(0);
            $table->enum('status', ['OPEN', 'APPLIED', 'CANCELLED'])->default('OPEN');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_invoice_deductions');
        Schema::dropIfExists('purchase_return_lines');
        Schema::dropIfExists('purchase_returns');
    }
};
