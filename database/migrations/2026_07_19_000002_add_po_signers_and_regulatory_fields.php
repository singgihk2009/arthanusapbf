<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('purchase_order_signers')) {
            Schema::create('purchase_order_signers', function (Blueprint $table) {
                $table->id();
                $table->string('po_type', 30)->index();
                $table->foreignId('requester_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('approver_employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('requester_name')->nullable();
                $table->string('requester_title')->nullable();
                $table->string('requester_license_no')->nullable();
                $table->string('approver_name')->nullable();
                $table->string('approver_title')->nullable();
                $table->string('approver_license_no')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_orders', 'signer_profile_id')) {
                $table->foreignId('signer_profile_id')->nullable()->after('po_type')->constrained('purchase_order_signers')->nullOnDelete();
            }
            if (!Schema::hasColumn('purchase_orders', 'usage_purpose')) {
                $table->string('usage_purpose')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('purchase_orders', 'warehouse_address')) {
                $table->text('warehouse_address')->nullable()->after('usage_purpose');
            }
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_items', 'active_ingredient')) {
                $table->string('active_ingredient')->nullable()->after('product_name');
            }
            if (!Schema::hasColumn('purchase_order_items', 'dosage_form_strength')) {
                $table->string('dosage_form_strength')->nullable()->after('active_ingredient');
            }
            if (!Schema::hasColumn('purchase_order_items', 'regulatory_note')) {
                $table->text('regulatory_note')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            foreach (['active_ingredient', 'dosage_form_strength', 'regulatory_note'] as $column) {
                if (Schema::hasColumn('purchase_order_items', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_orders', 'signer_profile_id')) {
                $table->dropConstrainedForeignId('signer_profile_id');
            }
            foreach (['usage_purpose', 'warehouse_address'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) $table->dropColumn($column);
            }
        });
        Schema::dropIfExists('purchase_order_signers');
    }
};
