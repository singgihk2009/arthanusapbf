<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('account_code', 50);
            $table->string('account_name');
            $table->string('account_type', 50)->default('asset');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'account_code'], 'coa_company_code_unique');
            $table->index(['company_id', 'account_type', 'is_active'], 'coa_company_type_active_idx');
        });

        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->enum('cash_type', ['CASH', 'BANK', 'CASH_EQUIVALENT'])->default('BANK');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code'], 'cash_accounts_company_code_unique');
            $table->index(['company_id', 'cash_type', 'is_active'], 'cash_accounts_company_type_active_idx');
        });

        Schema::table('vendor_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_payments', 'cash_account_id')) {
                $table->foreignId('cash_account_id')->nullable()->after('bank_account_id')->constrained('cash_accounts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_payments', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payments', 'cash_account_id')) {
                $table->dropConstrainedForeignId('cash_account_id');
            }
        });

        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
