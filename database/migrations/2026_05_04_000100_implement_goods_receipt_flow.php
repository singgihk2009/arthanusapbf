<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_orders', 'fulfillment_status')) {
                $table->string('fulfillment_status', 30)->default('open')->after('status');
                $table->index('fulfillment_status');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_order_items', 'received_qty')) {
                $table->decimal('received_qty', 20, 6)->default(0)->after('qty_ordered');
            }
            if (! Schema::hasColumn('purchase_order_items', 'remaining_qty')) {
                $table->decimal('remaining_qty', 20, 6)->nullable()->after('received_qty');
            }
            if (! Schema::hasColumn('purchase_order_items', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('remaining_qty');
            }
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipts', 'business_id')) $table->unsignedBigInteger('business_id')->default(1)->after('id');
            if (! Schema::hasColumn('goods_receipts', 'gr_number')) $table->string('gr_number')->nullable()->after('vendor_id');
            if (! Schema::hasColumn('goods_receipts', 'received_date')) $table->date('received_date')->nullable()->after('gr_number');
            if (! Schema::hasColumn('goods_receipts', 'received_by')) $table->unsignedBigInteger('received_by')->nullable()->after('received_date');
            if (! Schema::hasColumn('goods_receipts', 'created_by')) $table->unsignedBigInteger('created_by')->nullable()->after('received_by');
            if (! Schema::hasColumn('goods_receipts', 'updated_by')) $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
        });

        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
                $table->foreignId('product_id')->constrained('items')->restrictOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->decimal('ordered_qty', 20, 6)->default(0);
                $table->decimal('previously_received_qty', 20, 6)->default(0);
                $table->decimal('received_qty', 20, 6);
                $table->decimal('remaining_qty', 20, 6);
                $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
                $table->decimal('po_unit_price', 20, 6)->default(0);
                $table->decimal('inventory_unit_cost', 20, 6)->default(0);
                $table->decimal('inventory_total_cost', 20, 6)->default(0);
                $table->string('batch_number')->nullable();
                $table->date('expired_date')->nullable();
                $table->string('condition_status', 30)->default('good');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->default(1);
                $table->foreignId('product_id')->constrained('items')->restrictOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
                $table->string('reference_type', 40);
                $table->unsignedBigInteger('reference_id');
                $table->unsignedBigInteger('reference_item_id')->nullable();
                $table->date('movement_date');
                $table->string('direction', 5);
                $table->decimal('qty', 20, 6);
                $table->decimal('unit_cost', 20, 6);
                $table->decimal('total_cost', 20, 6);
                $table->string('batch_number')->nullable();
                $table->date('expired_date')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void {}
};
