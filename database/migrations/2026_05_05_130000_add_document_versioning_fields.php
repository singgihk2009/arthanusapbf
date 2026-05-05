<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('parent_document_id')->nullable()->after('id');
            $table->integer('version_number')->default(1)->after('status');
            $table->unsignedBigInteger('replaced_by_document_id')->nullable()->after('parent_document_id');
            $table->boolean('is_current')->default(true)->after('version_number');
            $table->string('version_type')->nullable()->after('is_current');

            $table->foreign('parent_document_id')->references('id')->on('documents')->nullOnDelete();
            $table->foreign('replaced_by_document_id')->references('id')->on('documents')->nullOnDelete();

            $table->index('parent_document_id');
            $table->index('replaced_by_document_id');
            $table->index(['owner_type', 'owner_id', 'document_type_id']);
            $table->index(['owner_type', 'owner_id', 'document_type_id', 'is_current'], 'documents_owner_current_idx');
            $table->index(['business_id', 'owner_type', 'owner_id', 'document_type_id', 'is_current'], 'documents_business_owner_current_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropForeign(['parent_document_id']);
            $table->dropForeign(['replaced_by_document_id']);
            $table->dropIndex(['parent_document_id']);
            $table->dropIndex(['replaced_by_document_id']);
            $table->dropIndex(['owner_type', 'owner_id', 'document_type_id']);
            $table->dropIndex('documents_owner_current_idx');
            $table->dropIndex('documents_business_owner_current_idx');

            $table->dropColumn([
                'parent_document_id',
                'replaced_by_document_id',
                'version_number',
                'is_current',
                'version_type',
            ]);
        });
    }
};
