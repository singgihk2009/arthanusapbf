<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_returns')) {
            Schema::table('purchase_returns', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_returns', 'receiving_entry_id')) {
                    $table->foreignId('receiving_entry_id')->nullable()->after('vendor_id')->constrained('receiving_entries')->restrictOnDelete();
                }
            });

            if (Schema::hasColumn('purchase_returns', 'goods_receipt_id')) {
                Schema::table('purchase_returns', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('goods_receipt_id');
                });
            }
        }

        if (Schema::hasTable('purchase_return_lines')) {
            Schema::table('purchase_return_lines', function (Blueprint $table): void {
                if (! Schema::hasColumn('purchase_return_lines', 'receiving_entry_line_id')) {
                    $table->foreignId('receiving_entry_line_id')->nullable()->after('purchase_return_id')->constrained('receiving_entry_lines')->restrictOnDelete();
                }
            });

            if (Schema::hasColumn('purchase_return_lines', 'goods_receipt_item_id')) {
                Schema::table('purchase_return_lines', function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('goods_receipt_item_id');
                });
            }
        }
    }

    public function down(): void
    {
        // No rollback: this migration only makes the new receiving_entries source compatible
        // with environments that may have run the previous draft purchase return migration.
    }
};
