<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors','vendor_name')) $table->string('vendor_name')->nullable()->after('vendor_code');
            if (!Schema::hasColumn('vendors','npwp')) $table->string('npwp')->nullable()->after('vendor_name');
            if (!Schema::hasColumn('vendors','is_pkp')) $table->boolean('is_pkp')->default(false)->after('npwp');
            if (!Schema::hasColumn('vendors','default_payment_term_id')) $table->foreignId('default_payment_term_id')->nullable()->after('status')->constrained('payment_terms')->nullOnDelete();
            if (!Schema::hasColumn('vendors','default_ap_account_id')) $table->unsignedBigInteger('default_ap_account_id')->nullable()->after('default_payment_term_id');
            if (!Schema::hasColumn('vendors','created_by')) $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendors','updated_by')) $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('vendor_contacts', function (Blueprint $table) {
            $table->id(); $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name'); $table->string('position')->nullable(); $table->string('phone')->nullable(); $table->string('email')->nullable();
            $table->timestamps();
        });
        Schema::create('vendor_bank_accounts', function (Blueprint $table) {
            $table->id(); $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('bank_name'); $table->string('account_number'); $table->string('account_name'); $table->boolean('is_default')->default(false); $table->timestamps();
        });
        Schema::create('vendor_ledgers', function (Blueprint $table) {
            $table->id(); $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete(); $table->date('transaction_date');
            $table->string('reference_type'); $table->unsignedBigInteger('reference_id'); $table->string('description')->nullable();
            $table->decimal('debit',20,6)->default(0); $table->decimal('credit',20,6)->default(0); $table->decimal('balance',20,6)->default(0);
            $table->string('status')->default('posted'); $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vendor_ledgers'); Schema::dropIfExists('vendor_bank_accounts'); Schema::dropIfExists('vendor_contacts');
    }
};