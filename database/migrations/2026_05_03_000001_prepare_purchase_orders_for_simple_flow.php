<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->string('po_number')->nullable()->after('id');
            }
            if (!Schema::hasColumn('purchase_orders', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable()->after('po_date');
            }
            if (!Schema::hasColumn('purchase_orders', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_orders', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            foreach (['subtotal', 'discount_total', 'tax_total', 'grand_total'] as $column) {
                $table->decimal($column, 18, 2)->default(0)->change();
            }
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'status')) {
                $table->string('status')->default('draft');
            }
            if (!Schema::hasColumn('purchase_orders', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->constrained('vendors')->restrictOnDelete();
            }
            if (Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->unique('po_number');
            }
        });

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('items')->nullOnDelete();
                $table->string('product_name')->nullable();
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('qty_ordered', 18, 2)->default(0);
                $table->decimal('qty_received', 18, 2)->default(0);
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->decimal('discount_amount', 18, 2)->default(0);
                $table->decimal('tax_amount', 18, 2)->default(0);
                $table->decimal('line_total', 18, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'po_number')) {
                $table->dropUnique(['po_number']);
                $table->dropColumn('po_number');
            }
            if (Schema::hasColumn('purchase_orders', 'expected_delivery_date')) {
                $table->dropColumn('expected_delivery_date');
            }
        });
    }
};
