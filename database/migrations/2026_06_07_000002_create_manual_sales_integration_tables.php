<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('manual_sales_integration_document_links');
        Schema::dropIfExists('manual_sales_integration_rows');
        Schema::dropIfExists('manual_sales_integration_batches');

        Schema::create('manual_sales_integration_batches', function (Blueprint $table): void {
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

            $table->index(['source_system', 'source_branch_code'], 'manual_sales_int_source_idx');
            $table->index('status', 'manual_sales_int_status_idx');
        });

        Schema::create('manual_sales_integration_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('manual_sales_integration_batches')->cascadeOnDelete();
            $table->string('sheet_name', 80);
            $table->unsignedInteger('row_number');
            $table->string('status', 30)->default('valid');
            $table->json('row_data_json')->nullable();
            $table->json('messages_json')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'sheet_name'], 'manual_sales_int_rows_sheet_idx');
        });

        Schema::create('manual_sales_integration_document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('manual_sales_integration_batches')->cascadeOnDelete();
            $table->string('source_system', 80);
            $table->string('source_branch_code', 80);
            $table->string('document_type', 60);
            $table->string('document_no', 120);
            $table->string('target_table', 80);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['source_system', 'source_branch_code', 'document_type', 'document_no'], 'manual_sales_int_doc_unique');
            $table->index(['target_table', 'target_id'], 'manual_sales_int_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_sales_integration_document_links');
        Schema::dropIfExists('manual_sales_integration_rows');
        Schema::dropIfExists('manual_sales_integration_batches');
    }
};
