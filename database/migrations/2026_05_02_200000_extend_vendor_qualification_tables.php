<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'vendor_type')) $table->string('vendor_type')->nullable()->after('vendor_name');
            if (!Schema::hasColumn('vendors', 'postal_code')) $table->string('postal_code')->nullable()->after('address');
            if (!Schema::hasColumn('vendors', 'village')) $table->string('village')->nullable()->after('postal_code');
            if (!Schema::hasColumn('vendors', 'district')) $table->string('district')->nullable()->after('village');
            if (!Schema::hasColumn('vendors', 'city')) $table->string('city')->nullable()->after('district');
            if (!Schema::hasColumn('vendors', 'province')) $table->string('province')->nullable()->after('city');
            if (!Schema::hasColumn('vendors', 'fax')) $table->string('fax')->nullable()->after('phone');
            if (!Schema::hasColumn('vendors', 'nib_number')) $table->string('nib_number')->nullable()->after('is_pkp');
            if (!Schema::hasColumn('vendors', 'company_license_number')) $table->string('company_license_number')->nullable()->after('nib_number');
            if (!Schema::hasColumn('vendors', 'cdakb_cpakb_certificate_number')) $table->string('cdakb_cpakb_certificate_number')->nullable()->after('company_license_number');
            if (!Schema::hasColumn('vendors', 'cdakb_cpakb_certificate_expiry_date')) $table->date('cdakb_cpakb_certificate_expiry_date')->nullable()->after('cdakb_cpakb_certificate_number');
            if (!Schema::hasColumn('vendors', 'qualification_status')) $table->string('qualification_status')->default('draft')->after('status');
            if (!Schema::hasColumn('vendors', 'qualification_date')) $table->date('qualification_date')->nullable()->after('qualification_status');
            if (!Schema::hasColumn('vendors', 'verified_by')) $table->foreignId('verified_by')->nullable()->after('qualification_date')->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendors', 'verified_at')) $table->timestamp('verified_at')->nullable()->after('verified_by');
            if (!Schema::hasColumn('vendors', 'notes')) $table->text('notes')->nullable()->after('verified_at');
            if (!Schema::hasColumn('vendors', 'approved_by')) $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendors', 'rejected_by')) $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendors', 'submitted_by')) $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendors', 'submitted_at')) $table->timestamp('submitted_at')->nullable();
            if (!Schema::hasColumn('vendors', 'approved_at')) $table->timestamp('approved_at')->nullable();
            if (!Schema::hasColumn('vendors', 'rejected_at')) $table->timestamp('rejected_at')->nullable();
        });

        Schema::table('vendor_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_contacts', 'contact_type')) $table->string('contact_type')->default('other')->after('vendor_id');
            if (!Schema::hasColumn('vendor_contacts', 'address')) $table->text('address')->nullable()->after('name');
            if (!Schema::hasColumn('vendor_contacts', 'mobile_phone')) $table->string('mobile_phone')->nullable()->after('phone');
            if (!Schema::hasColumn('vendor_contacts', 'position')) $table->string('position')->nullable()->after('mobile_phone');
            if (!Schema::hasColumn('vendor_contacts', 'license_number')) $table->string('license_number')->nullable()->after('position');
            if (!Schema::hasColumn('vendor_contacts', 'license_expiry_date')) $table->date('license_expiry_date')->nullable()->after('license_number');
            if (!Schema::hasColumn('vendor_contacts', 'is_primary')) $table->boolean('is_primary')->default(false)->after('license_expiry_date');
            if (!Schema::hasColumn('vendor_contacts', 'notes')) $table->text('notes')->nullable()->after('is_primary');
            if (!Schema::hasColumn('vendor_contacts', 'created_by')) $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendor_contacts', 'updated_by')) $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            if (!Schema::hasColumn('vendor_contacts', 'deleted_at')) $table->softDeletes();
        });

        if (!Schema::hasTable('vendor_documents')) {
            Schema::create('vendor_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('document_type');
                $table->string('document_name')->nullable();
                $table->string('document_number')->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('file_path')->nullable();
                $table->string('original_filename')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('verification_status')->default('pending');
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
