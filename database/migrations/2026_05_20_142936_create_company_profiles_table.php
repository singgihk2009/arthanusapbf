<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('tax_id', 30)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Indonesia');
            $table->string('logo_path')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->text('invoice_terms')->nullable();
            $table->timestamps();
            $table->unique('party_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
