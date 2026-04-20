<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('vendor_code')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_id')->nullable();
            $table->foreignId('payment_term_id')->nullable()->constrained('payment_terms')->nullOnDelete();
            $table->string('currency_code', 3)->default('IDR');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status'], 'vendors_company_status_idx');
        });

        DB::table('suppliers')->orderBy('id')->get()->each(function ($supplier): void {
            DB::table('vendors')->insert([
                'id' => $supplier->id,
                'company_id' => 1,
                'vendor_code' => $supplier->code,
                'name' => $supplier->name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'currency_code' => 'IDR',
                'status' => (bool) $supplier->is_active ? 'ACTIVE' : 'INACTIVE',
                'created_at' => $supplier->created_at,
                'updated_at' => $supplier->updated_at,
                'deleted_at' => $supplier->deleted_at,
            ]);
        });

        DB::statement('ALTER TABLE vendors AUTO_INCREMENT = 100000');

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->default(1)->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('warehouse_id');
            $table->string('po_no')->nullable()->after('vendor_id');
            $table->date('po_date')->nullable()->after('po_no');
            $table->string('currency_code', 3)->default('IDR')->after('expected_date');
            $table->foreignId('payment_term_id')->nullable()->after('exchange_rate')->constrained('payment_terms')->nullOnDelete();
            $table->decimal('other_amount', 20, 6)->default(0)->after('tax_total');
            $table->string('approval_status', 30)->default('PENDING')->after('status');
            $table->foreignId('created_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('sent_at');
            $table->timestamp('cancelled_at')->nullable()->after('closed_at');

            $table->index('vendor_id', 'purchase_orders_vendor_id_idx');
            $table->index('status', 'purchase_orders_status_idx');
            $table->index('po_date', 'purchase_orders_po_date_idx');
            $table->unique('po_no', 'purchase_orders_po_no_unique');
        });

        DB::table('purchase_orders')->update([
            'vendor_id' => DB::raw('supplier_id'),
            'po_no' => DB::raw('number'),
            'po_date' => DB::raw('document_date'),
            'currency_code' => DB::raw('currency'),
            'other_amount' => DB::raw('freight_total'),
            'approval_status' => DB::raw("CASE WHEN approved_at IS NOT NULL THEN 'APPROVED' ELSE 'PENDING' END"),
        ]);

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->enum('item_type', ['stock', 'service', 'expense'])->default('stock')->after('item_id');
            $table->text('description')->nullable()->after('item_type');
            $table->decimal('qty_received', 20, 6)->default(0)->after('qty_ordered');
            $table->decimal('qty_invoiced', 20, 6)->default(0)->after('qty_received');
            $table->date('expected_date')->nullable()->after('line_total');
            $table->string('line_status', 30)->default('OPEN')->after('expected_date');
        });

        DB::table('purchase_order_lines')->update([
            'qty_received' => DB::raw('qty_received_base'),
            'description' => DB::raw("CONCAT('PO line item #', item_id)"),
        ]);

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->default(1)->after('id');
            $table->unsignedBigInteger('branch_id')->nullable()->after('company_id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('warehouse_id');
            $table->unsignedBigInteger('po_id')->nullable()->after('vendor_id');
            $table->string('receipt_no')->nullable()->after('po_id');
            $table->date('receipt_date')->nullable()->after('receipt_no');
            $table->string('delivery_note_no')->nullable()->after('receipt_date');

            $table->index('po_id', 'goods_receipts_po_id_idx');
            $table->index('receipt_date', 'goods_receipts_receipt_date_idx');
        });

        DB::table('goods_receipts')->update([
            'vendor_id' => DB::raw('supplier_id'),
            'po_id' => DB::raw('purchase_order_id'),
            'receipt_no' => DB::raw('number'),
            'receipt_date' => DB::raw('document_date'),
        ]);

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('po_line_id')->nullable()->after('goods_receipt_id');
            $table->decimal('qty_rejected', 20, 6)->default(0)->after('qty_received');
            $table->decimal('qty_accepted', 20, 6)->default(0)->after('qty_rejected');
            $table->decimal('unit_cost_reference', 20, 6)->default(0)->after('qty_accepted');
            $table->date('manufacture_date')->nullable()->after('expired_date');
            $table->string('rack_location')->nullable()->after('manufacture_date');

            $table->index('po_line_id', 'goods_receipt_lines_po_line_id_idx');
        });

        DB::table('goods_receipt_lines')->update([
            'po_line_id' => DB::raw('purchase_order_line_id'),
            'qty_accepted' => DB::raw('qty_received'),
            'unit_cost_reference' => DB::raw('unit_price'),
        ]);

        Schema::create('vendor_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('invoice_no_internal')->unique();
            $table->string('vendor_invoice_no');
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('exchange_rate', 20, 6)->default(1);
            $table->decimal('subtotal', 20, 6)->default(0);
            $table->decimal('tax_amount', 20, 6)->default(0);
            $table->decimal('discount_amount', 20, 6)->default(0);
            $table->decimal('freight_amount', 20, 6)->default(0);
            $table->decimal('grand_total', 20, 6)->default(0);
            $table->decimal('paid_amount', 20, 6)->default(0);
            $table->decimal('outstanding_amount', 20, 6)->default(0);
            $table->enum('status', ['DRAFT', 'POSTED', 'PARTIAL_PAID', 'PAID', 'VOID'])->default('DRAFT');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('due_date');
            $table->index('status');
        });

        Schema::create('vendor_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->cascadeOnDelete();
            $table->foreignId('receipt_line_id')->nullable()->constrained('goods_receipt_lines')->nullOnDelete();
            $table->foreignId('po_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('qty_invoiced', 20, 6)->default(0);
            $table->decimal('unit_price', 20, 6)->default(0);
            $table->decimal('tax_amount', 20, 6)->default(0);
            $table->decimal('line_total', 20, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->string('payment_no')->unique();
            $table->date('payment_date');
            $table->enum('payment_method', ['CASH', 'BANK_TRANSFER', 'GIRO', 'CHEQUE', 'OTHER'])->default('BANK_TRANSFER');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('exchange_rate', 20, 6)->default(1);
            $table->decimal('total_amount', 20, 6)->default(0);
            $table->decimal('allocated_amount', 20, 6)->default(0);
            $table->decimal('unapplied_amount', 20, 6)->default(0);
            $table->enum('status', ['DRAFT', 'POSTED', 'VOID'])->default('DRAFT');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('payment_date');
        });

        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained('vendor_payments')->cascadeOnDelete();
            $table->foreignId('vendor_invoice_id')->constrained('vendor_invoices')->restrictOnDelete();
            $table->decimal('allocated_amount', 20, 6);
            $table->timestamps();

            $table->unique(['vendor_payment_id', 'vendor_invoice_id'], 'vendor_payment_invoice_unique');
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'document_links_source_idx');
            $table->index(['target_type', 'target_id'], 'document_links_target_idx');
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('document_id');
            $table->string('action', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->index(['document_type', 'document_id'], 'approval_logs_doc_idx');
        });

        Schema::table('inv_transactions', function (Blueprint $table) {
            $table->string('reference_type', 40)->nullable()->after('source_id');
            $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');

            $table->index(['reference_type', 'reference_id'], 'inv_transactions_reference_idx');
        });

        Schema::table('inv_transaction_items', function (Blueprint $table) {
            $table->decimal('qty_accepted', 20, 6)->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('inv_transaction_items', function (Blueprint $table) {
            $table->dropColumn('qty_accepted');
        });

        Schema::table('inv_transactions', function (Blueprint $table) {
            $table->dropIndex('inv_transactions_reference_idx');
            $table->dropColumn(['reference_type', 'reference_id']);
        });

        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('vendor_payment_allocations');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_invoice_lines');
        Schema::dropIfExists('vendor_invoices');

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropIndex('goods_receipt_lines_po_line_id_idx');
            $table->dropColumn(['po_line_id', 'qty_rejected', 'qty_accepted', 'unit_cost_reference', 'manufacture_date', 'rack_location']);
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropIndex('goods_receipts_po_id_idx');
            $table->dropIndex('goods_receipts_receipt_date_idx');
            $table->dropColumn(['company_id', 'branch_id', 'vendor_id', 'po_id', 'receipt_no', 'receipt_date', 'delivery_note_no']);
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['item_type', 'description', 'qty_received', 'qty_invoiced', 'expected_date', 'line_status']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique('purchase_orders_po_no_unique');
            $table->dropIndex('purchase_orders_vendor_id_idx');
            $table->dropIndex('purchase_orders_status_idx');
            $table->dropIndex('purchase_orders_po_date_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('payment_term_id');
            $table->dropColumn(['company_id', 'branch_id', 'vendor_id', 'po_no', 'po_date', 'currency_code', 'other_amount', 'approval_status', 'closed_at', 'cancelled_at']);
        });

        Schema::dropIfExists('vendors');
        Schema::dropIfExists('payment_terms');
    }
};
