<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_payments', 'wht_amount')) {
                $table->decimal('wht_amount', 18, 2)->default(0)->after('discount_taken');
            }
            if (! Schema::hasColumn('customer_payments', 'other_deduction_amount')) {
                $table->decimal('other_deduction_amount', 18, 2)->default(0)->after('wht_amount');
            }
            if (! Schema::hasColumn('customer_payments', 'gross_settlement_amount')) {
                $table->decimal('gross_settlement_amount', 18, 2)->default(0)->after('other_deduction_amount');
            }
        });

        Schema::table('customer_payment_allocations', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_payment_allocations', 'wht_amount')) {
                $table->decimal('wht_amount', 18, 2)->default(0)->after('discount_taken');
            }
            if (! Schema::hasColumn('customer_payment_allocations', 'other_deduction_amount')) {
                $table->decimal('other_deduction_amount', 18, 2)->default(0)->after('wht_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_payment_allocations', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_payment_allocations', 'other_deduction_amount')) {
                $table->dropColumn('other_deduction_amount');
            }
            if (Schema::hasColumn('customer_payment_allocations', 'wht_amount')) {
                $table->dropColumn('wht_amount');
            }
        });

        Schema::table('customer_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_payments', 'gross_settlement_amount')) {
                $table->dropColumn('gross_settlement_amount');
            }
            if (Schema::hasColumn('customer_payments', 'other_deduction_amount')) {
                $table->dropColumn('other_deduction_amount');
            }
            if (Schema::hasColumn('customer_payments', 'wht_amount')) {
                $table->dropColumn('wht_amount');
            }
        });
    }
};
