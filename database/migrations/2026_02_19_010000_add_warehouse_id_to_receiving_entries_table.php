<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('receiving_entries')) {
            return;
        }

        if (! Schema::hasColumn('receiving_entries', 'warehouse_id')) {
            Schema::table('receiving_entries', function (Blueprint $table): void {
                $table->foreignId('warehouse_id')->nullable()->after('number');
            });
        }

        if (Schema::hasColumn('receiving_entries', 'warehouse_code')) {
            DB::statement('UPDATE receiving_entries re JOIN warehouses w ON w.code = re.warehouse_code SET re.warehouse_id = w.id WHERE re.warehouse_id IS NULL');
        }

        if (Schema::hasColumn('receiving_entries', 'kode_gudang')) {
            DB::statement('UPDATE receiving_entries re JOIN warehouses w ON w.code = re.kode_gudang SET re.warehouse_id = w.id WHERE re.warehouse_id IS NULL');
        }

        if (Schema::hasColumn('receiving_entries', 'gudang_id')) {
            DB::statement('UPDATE receiving_entries SET warehouse_id = gudang_id WHERE warehouse_id IS NULL');
        }

        if (Schema::hasColumn('receiving_entries', 'id_gudang')) {
            DB::statement('UPDATE receiving_entries SET warehouse_id = id_gudang WHERE warehouse_id IS NULL');
        }

        if (Schema::hasColumn('receiving_entries', 'warehouse')) {
            DB::statement('UPDATE receiving_entries re JOIN warehouses w ON w.code = re.warehouse SET re.warehouse_id = w.id WHERE re.warehouse_id IS NULL');
        }

        if (Schema::hasColumn('receiving_entries', 'gudang')) {
            DB::statement('UPDATE receiving_entries re JOIN warehouses w ON w.code = re.gudang SET re.warehouse_id = w.id WHERE re.warehouse_id IS NULL');
        }

        if (! $this->hasWarehouseForeignKey()) {
            Schema::table('receiving_entries', function (Blueprint $table): void {
                $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('receiving_entries') || ! Schema::hasColumn('receiving_entries', 'warehouse_id')) {
            return;
        }

        if ($this->hasWarehouseForeignKey()) {
            Schema::table('receiving_entries', function (Blueprint $table): void {
                $table->dropForeign(['warehouse_id']);
            });
        }

        Schema::table('receiving_entries', function (Blueprint $table): void {
            $table->dropColumn('warehouse_id');
        });
    }

    private function hasWarehouseForeignKey(): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'receiving_entries')
            ->where('COLUMN_NAME', 'warehouse_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
