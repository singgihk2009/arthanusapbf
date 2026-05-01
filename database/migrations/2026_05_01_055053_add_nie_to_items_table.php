<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (! Schema::hasColumn('items', 'nie')) {
                $table->string('nie')->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            if (Schema::hasColumn('items', 'nie')) {
                $table->dropColumn('nie');
            }
        });
    }
};
