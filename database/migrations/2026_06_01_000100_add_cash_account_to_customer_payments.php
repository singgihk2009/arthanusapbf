<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('customer_payments', 'cash_account_id')) {
                $table->foreignId('cash_account_id')
                    ->nullable()
                    ->after('bank_account_id')
                    ->constrained('cash_accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('customer_payments', 'cash_account_id')) {
                $table->dropConstrainedForeignId('cash_account_id');
            }
        });
    }
};
