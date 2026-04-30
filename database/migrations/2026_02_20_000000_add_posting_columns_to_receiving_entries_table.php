<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('receiving_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('receiving_entries', 'status')) {
                $table->string('status', 20)->default('DRAFT')->after('total_value');
            }

            if (! Schema::hasColumn('receiving_entries', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('receiving_entries', 'posted_by')) {
                $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('receiving_entries', function (Blueprint $table) {
            if (Schema::hasColumn('receiving_entries', 'posted_by')) {
                $table->dropConstrainedForeignId('posted_by');
            }

            if (Schema::hasColumn('receiving_entries', 'posted_at')) {
                $table->dropColumn('posted_at');
            }

            if (Schema::hasColumn('receiving_entries', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};

