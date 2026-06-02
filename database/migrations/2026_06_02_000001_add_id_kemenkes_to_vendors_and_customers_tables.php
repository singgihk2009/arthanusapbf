<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (!Schema::hasColumn('vendors', 'id_kemenkes')) {
                $table->string('id_kemenkes', 100)->nullable()->after('vendor_type');
                $table->index('id_kemenkes', 'vendors_id_kemenkes_idx');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (!Schema::hasColumn('customers', 'id_kemenkes')) {
                $table->string('id_kemenkes', 100)->nullable()->after('customer_type');
                $table->index('id_kemenkes', 'customers_id_kemenkes_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table): void {
            if (Schema::hasColumn('vendors', 'id_kemenkes')) {
                $table->dropIndex('vendors_id_kemenkes_idx');
                $table->dropColumn('id_kemenkes');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'id_kemenkes')) {
                $table->dropIndex('customers_id_kemenkes_idx');
                $table->dropColumn('id_kemenkes');
            }
        });
    }
};
