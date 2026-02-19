<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('receiving_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->date('transaction_date');
            $table->enum('transaction_code', ['PEMBELIAN', 'RETUR', 'ADJUSTMENT']);
            $table->string('reference')->nullable();
            $table->string('vendor_name')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_value', 20, 6)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('receiving_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receiving_entry_id')->constrained('receiving_entries')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('qty', 20, 6);
            $table->decimal('price', 20, 6)->default(0);
            $table->decimal('value', 20, 6)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receiving_entry_lines');
        Schema::dropIfExists('receiving_entries');
    }
};
