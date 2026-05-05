<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            }
        });

        Schema::table('document_audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('document_audit_logs', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('document_audit_logs', 'created_at')) {
                $table->dropTimestamps();
            }
        });

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }
            if (Schema::hasColumn('documents', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
