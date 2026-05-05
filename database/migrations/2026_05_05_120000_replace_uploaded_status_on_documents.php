<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documents')
            ->where('status', 'uploaded')
            ->update(['status' => 'pending_review']);
    }

    public function down(): void
    {
        DB::table('documents')
            ->where('status', 'pending_review')
            ->update(['status' => 'uploaded']);
    }
};
