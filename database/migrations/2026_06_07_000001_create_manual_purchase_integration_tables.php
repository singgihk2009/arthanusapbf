<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // This migration may be retried after a failed unique-index creation on
        // older MySQL/utf8mb4 configurations, so clear any partial tables first.
        Schema::dropIfExists('manual_purchase_integration_document_links');
        Schema::dropIfExists('manual_purchase_integration_rows');
        Schema::dropIfExists('manual_purchase_integration_batches');

        Schema::create('manual_purchase_integration_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_no')->unique();
            $table->string('source_system', 80);
            $table->string('source_branch_code', 80);
            $table->string('import_purpose')->default('BRANCH_INTEGRATION');
            $table->string('file_name')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->string('status', 30)->default('validated');
            $table->json('summary_json')->nullable();
            $table->json('errors_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('preview_json')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('committed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['source_system', 'source_branch_code'], 'manual_purchase_int_source_idx');
            $table->index('status', 'manual_purchase_int_status_idx');
        });

        Schema::create('manual_purchase_integration_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('manual_purchase_integration_batches')->cascadeOnDelete();
            $table->string('sheet_name', 80);
            $table->unsignedInteger('row_number');
            $table->string('status', 30)->default('valid');
            $table->json('row_data_json')->nullable();
            $table->json('messages_json')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'sheet_name'], 'manual_purchase_int_rows_sheet_idx');
        });

        Schema::create('manual_purchase_integration_document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('manual_purchase_integration_batches')->cascadeOnDelete();
            $table->string('source_system', 80);
            $table->string('source_branch_code', 80);
            $table->string('document_type', 60);
            $table->string('document_no', 120);
            $table->string('target_table', 80);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['source_system', 'source_branch_code', 'document_type', 'document_no'], 'manual_purchase_int_doc_unique');
            $table->index(['target_table', 'target_id'], 'manual_purchase_int_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_purchase_integration_document_links');
        Schema::dropIfExists('manual_purchase_integration_rows');
        Schema::dropIfExists('manual_purchase_integration_batches');
    }
};
