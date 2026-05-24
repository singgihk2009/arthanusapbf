<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales', 'warehouse_id')) $table->foreignId('warehouse_id')->nullable()->change();
            if (!Schema::hasColumn('sales', 'submitted_by')) $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('sales', 'submitted_at')) $table->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn('sales', 'cancelled_by')) $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('sales', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable();
            if (!Schema::hasColumn('sales', 'cancel_reason')) $table->text('cancel_reason')->nullable();
            if (!Schema::hasColumn('sales', 'credit_status')) $table->string('credit_status')->nullable();
            if (!Schema::hasColumn('sales', 'credit_checked_at')) $table->timestamp('credit_checked_at')->nullable();
        });
        DB::table('sales')->where('status','DRAFT')->update(['status'=>'draft']);
        DB::table('sales')->where('status','SUBMITTED')->update(['status'=>'submitted']);
        DB::table('sales')->where('status','APPROVED')->update(['status'=>'approved']);
        DB::table('sales')->where('status','CANCELLED')->update(['status'=>'cancelled']);
    }

    public function down(): void {}
};
