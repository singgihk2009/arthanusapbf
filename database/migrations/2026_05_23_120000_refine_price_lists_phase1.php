<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('price_lists')) {
            Schema::table('price_lists', function (Blueprint $table): void {
                if (Schema::hasColumn('price_lists', 'customer_group')) {
                    $table->dropColumn('customer_group');
                }
                $table->index('name');
                $table->index('status');
                $table->index('effective_from');
                $table->index('effective_to');
                $table->index('is_default');
            });
        }

        if (Schema::hasTable('price_list_lines')) {
            Schema::table('price_list_lines', function (Blueprint $table): void {
                $table->decimal('min_qty', 18, 4)->default(1)->change();
                $table->index('price_list_id');
                $table->index('item_id');
                $table->index('uom_id');
                $table->index('status');
                $table->index('min_qty');
            });
        }
    }

    public function down(): void
    {
    }
};
