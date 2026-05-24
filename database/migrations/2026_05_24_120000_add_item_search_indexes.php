<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->addIndexIfNeeded('items', 'default_barcode', 'items_default_barcode_index');
        $this->addIndexIfNeeded('items', 'name', 'items_name_index');
        $this->addIndexIfNeeded('item_barcodes', 'barcode', 'item_barcodes_barcode_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('items', 'items_default_barcode_index');
        $this->dropIndexIfExists('items', 'items_name_index');
        $this->dropIndexIfExists('item_barcodes', 'item_barcodes_barcode_index');
    }

    private function addIndexIfNeeded(string $table, string $column, string $index): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $index) {
            $tableBlueprint->index($column, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $tableBlueprint) use ($index) {
            $tableBlueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('$table')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
            return false;
        }

        $database = DB::getDatabaseName();
        $rows = DB::select('SELECT index_name FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1', [$database, $table, $index]);

        return !empty($rows);
    }
};
