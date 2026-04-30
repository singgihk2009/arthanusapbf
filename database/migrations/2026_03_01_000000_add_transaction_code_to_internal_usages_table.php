<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internal_usages', function (Blueprint $table) {
            $table->enum('transaction_code', ['PENJUALAN', 'RETUR', 'DAMAGED', 'SAMPLE', 'INTERNAL_USE'])
                ->default('INTERNAL_USE')
                ->after('document_date');
        });
    }

    public function down(): void
    {
        Schema::table('internal_usages', function (Blueprint $table) {
            $table->dropColumn('transaction_code');
        });
    }
};
