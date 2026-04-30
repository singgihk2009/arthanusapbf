<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('regulatory_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_name')->unique();
            $table->timestamps();
        });

        Schema::create('regulatory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('regulatory_sources')->cascadeOnDelete();
            $table->string('nie');
            $table->string('product_name_source');
            $table->string('industry_name')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('strength')->nullable();
            $table->string('commodity_type')->nullable();
            $table->text('raw_packaging_text')->nullable();
            $table->text('raw_composition_text')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('nie');
            $table->unique(['source_id', 'nie']);
        });

        Schema::create('product_compositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulatory_product_id')->constrained('regulatory_products')->cascadeOnDelete();
            $table->string('substance_name');
            $table->string('strength')->nullable();
            $table->string('unit')->nullable();
            $table->timestamps();
            $table->index('substance_name');
        });

        Schema::create('product_packagings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulatory_product_id')->constrained('regulatory_products')->cascadeOnDelete();
            $table->string('packaging_type')->nullable();
            $table->decimal('outer_qty', 20, 6)->nullable();
            $table->string('outer_unit')->nullable();
            $table->decimal('inner_qty', 20, 6)->nullable();
            $table->string('inner_unit')->nullable();
            $table->decimal('content_qty', 20, 6)->nullable();
            $table->string('content_unit')->nullable();
            $table->text('description_raw')->nullable();
            $table->timestamps();
        });

        Schema::create('item_regulatory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('regulatory_product_id')->constrained('regulatory_products')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'regulatory_product_id']);
        });

        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('alias_name');
            $table->string('source')->nullable();
            $table->timestamps();
            $table->index('alias_name');
        });

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'regulatory_name')) $table->string('regulatory_name')->nullable()->after('name');
            if (!Schema::hasColumn('items', 'market_name')) $table->string('market_name')->nullable()->after('regulatory_name');
            if (!Schema::hasColumn('items', 'dosage_form')) $table->string('dosage_form')->nullable()->after('market_name');
            if (!Schema::hasColumn('items', 'strength')) $table->string('strength')->nullable()->after('dosage_form');
            if (!Schema::hasColumn('items', 'commodity_type')) $table->string('commodity_type')->nullable()->after('strength');
            if (!Schema::hasColumn('items', 'requires_batch_tracking')) $table->boolean('requires_batch_tracking')->default(true)->after('commodity_type');
            if (!Schema::hasColumn('items', 'requires_expiry_tracking')) $table->boolean('requires_expiry_tracking')->default(true)->after('requires_batch_tracking');
        });

        DB::table('regulatory_sources')->insertOrIgnore([
            ['source_name' => 'BPOM', 'created_at' => now(), 'updated_at' => now()],
            ['source_name' => 'KEMENKES', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
        Schema::dropIfExists('item_regulatory_products');
        Schema::dropIfExists('product_packagings');
        Schema::dropIfExists('product_compositions');
        Schema::dropIfExists('regulatory_products');
        Schema::dropIfExists('regulatory_sources');
    }
};
