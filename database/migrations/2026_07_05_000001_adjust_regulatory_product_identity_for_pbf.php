<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('regulatory_products')) {
            return;
        }

        if ($this->indexExists('regulatory_products', 'regulatory_products_source_id_nie_unique')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->dropUnique('regulatory_products_source_id_nie_unique');
            });
        }

        if (! $this->indexExists('regulatory_products', 'regulatory_products_source_type_nie_code_unique')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->unique(['source_id', 'product_type', 'nie', 'source_code'], 'regulatory_products_source_type_nie_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('regulatory_products')) {
            return;
        }

        if ($this->indexExists('regulatory_products', 'regulatory_products_source_type_nie_code_unique')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->dropUnique('regulatory_products_source_type_nie_code_unique');
            });
        }

        if (! $this->indexExists('regulatory_products', 'regulatory_products_source_id_nie_unique')) {
            Schema::table('regulatory_products', function (Blueprint $table) {
                $table->unique(['source_id', 'nie'], 'regulatory_products_source_id_nie_unique');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");
            return collect($indexes)->contains(fn ($index) => ($index->name ?? null) === $indexName);
        }

        $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return ! empty($result);
    }
};
