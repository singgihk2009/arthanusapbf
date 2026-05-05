<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->string('owner_type');
            $table->foreignId('document_type_id')->constrained('document_types');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_expirable')->default(false);
            $table->boolean('requires_verification')->default(false);
            $table->integer('reminder_days_before_expiry')->nullable()->default(30);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('business_id');
            $table->index('owner_type');
            $table->index('document_type_id');
            $table->index(['owner_type','document_type_id']);
            $table->unique(['business_id','owner_type','document_type_id'],'doc_req_business_owner_type_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('document_requirements'); }
};
