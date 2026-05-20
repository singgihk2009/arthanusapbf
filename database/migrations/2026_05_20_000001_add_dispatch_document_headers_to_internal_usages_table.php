<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_usages', function (Blueprint $table): void {
            if (! Schema::hasColumn('internal_usages', 'facility_scheme_id')) {
                $table->foreignId('facility_scheme_id')->nullable()->after('warehouse_id')->constrained('facility_schemes')->nullOnDelete();
            }

            if (! Schema::hasColumn('internal_usages', 'outbound_number')) {
                $table->string('outbound_number')->nullable()->after('transaction_code');
            }

            if (! Schema::hasColumn('internal_usages', 'sender_receiver_name')) {
                $table->string('sender_receiver_name')->nullable()->after('outbound_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('internal_usages', function (Blueprint $table): void {
            if (Schema::hasColumn('internal_usages', 'facility_scheme_id')) {
                $table->dropConstrainedForeignId('facility_scheme_id');
            }

            if (Schema::hasColumn('internal_usages', 'outbound_number')) {
                $table->dropColumn('outbound_number');
            }

            if (Schema::hasColumn('internal_usages', 'sender_receiver_name')) {
                $table->dropColumn('sender_receiver_name');
            }
        });
    }
};
