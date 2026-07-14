<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_types', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 20);
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('prefix', 20);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category', 'code']);
            $table->unique(['category', 'prefix']);
        });

        $now = now();
        DB::table('party_types')->insert([
            ['category' => 'vendor', 'code' => 'IF', 'name' => 'Industri Farmasi', 'prefix' => 'IF', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'vendor', 'code' => 'VMED', 'name' => 'Distributor/Pedagang Besar Farmasi untuk obat', 'prefix' => 'VMED', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'vendor', 'code' => 'VALK', 'name' => 'Produsen Alat Kesehatan', 'prefix' => 'VALK', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'vendor', 'code' => 'VOTH', 'name' => 'Distributor/Penyalur Alat Lainnya', 'prefix' => 'VOTH', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'KL', 'name' => 'Klinik', 'prefix' => 'KL', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'AP', 'name' => 'Apotek', 'prefix' => 'AP', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'TO', 'name' => 'Toko Obat', 'prefix' => 'TO', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'PBF', 'name' => 'PBF', 'prefix' => 'PBF', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'RS', 'name' => 'RS', 'prefix' => 'RS', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'IP', 'name' => 'Instansi Pemerintah', 'prefix' => 'IP', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'PKM', 'name' => 'Puskesmas', 'prefix' => 'PKM', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['category' => 'customer', 'code' => 'OTH', 'name' => 'Lainnya', 'prefix' => 'OTH', 'is_active' => true, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('party_types');
    }
};
