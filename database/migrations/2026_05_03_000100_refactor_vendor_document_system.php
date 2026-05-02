<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('document_types')) {
            Schema::create('document_types', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('category')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_critical')->default(false);
                $table->boolean('blocks_transaction')->default(false);
                $table->boolean('requires_expiry_date')->default(true);
                $table->integer('default_validity_days')->nullable();
                $table->string('applicable_vendor_type')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        $seed = [
            ['code' => 'NIB', 'name' => 'NIB', 'category' => 'legal'],
            ['code' => 'COMPANY_LICENSE', 'name' => 'Company License', 'category' => 'legal'],
            ['code' => 'CDAKB_CERTIFICATE', 'name' => 'CDAKB Certificate', 'category' => 'regulatory'],
            ['code' => 'CPAKB_CERTIFICATE', 'name' => 'CPAKB Certificate', 'category' => 'regulatory'],
            ['code' => 'SIP_PJT', 'name' => 'SIP PJT', 'category' => 'regulatory'],
            ['code' => 'NPWP', 'name' => 'NPWP', 'category' => 'tax'],
            ['code' => 'ISO_9001', 'name' => 'ISO 9001', 'category' => 'certification'],
            ['code' => 'IMPORT_LICENSE', 'name' => 'Import License', 'category' => 'regulatory'],
            ['code' => 'OTHER', 'name' => 'Other', 'category' => 'other'],
        ];

        foreach ($seed as $i => $item) {
            DB::table('document_types')->updateOrInsert(['code' => $item['code']], array_merge($item, ['sort_order' => $i + 1, 'updated_at' => now(), 'created_at' => now()]));
        }

        if (Schema::hasTable('vendor_documents')) {
            Schema::table('vendor_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_documents', 'document_type_id')) {
                    $table->foreignId('document_type_id')->nullable()->after('vendor_id')->constrained('document_types');
                }
                foreach ([
                    'document_number' => fn () => $table->string('document_number')->nullable(),
                    'issue_date' => fn () => $table->date('issue_date')->nullable(),
                    'expiry_date' => fn () => $table->date('expiry_date')->nullable(),
                    'file_path' => fn () => $table->string('file_path')->nullable(),
                    'original_filename' => fn () => $table->string('original_filename')->nullable(),
                    'mime_type' => fn () => $table->string('mime_type')->nullable(),
                    'file_size' => fn () => $table->unsignedBigInteger('file_size')->nullable(),
                    'verification_status' => fn () => $table->string('verification_status')->default('pending'),
                    'verified_by' => fn () => $table->unsignedBigInteger('verified_by')->nullable(),
                    'verified_at' => fn () => $table->timestamp('verified_at')->nullable(),
                    'notes' => fn () => $table->text('notes')->nullable(),
                    'created_by' => fn () => $table->unsignedBigInteger('created_by')->nullable(),
                    'updated_by' => fn () => $table->unsignedBigInteger('updated_by')->nullable(),
                ] as $col => $cb) {
                    if (!Schema::hasColumn('vendor_documents', $col)) $cb();
                }
            });

            if (Schema::hasColumn('vendor_documents', 'document_type')) {
                DB::table('vendor_documents')->whereNull('document_type_id')->orderBy('id')->chunkById(100, function ($rows) {
                    $map = [
                        'NIB' => 'NIB', 'COMPANY_LICENSE' => 'COMPANY_LICENSE', 'CDAKB_CERTIFICATE' => 'CDAKB_CERTIFICATE',
                        'CPAKB_CERTIFICATE' => 'CPAKB_CERTIFICATE', 'TECHNICAL_RESPONSIBLE_PERSON_SIP' => 'SIP_PJT',
                        'NPWP' => 'NPWP', 'OTHER' => 'OTHER',
                    ];
                    foreach ($rows as $row) {
                        $legacy = (string) $row->document_type;
                        $code = $map[$legacy] ?? Str::upper(Str::snake($legacy));
                        $typeId = DB::table('document_types')->where('code', $code)->value('id');
                        if (!$typeId) {
                            $typeId = DB::table('document_types')->insertGetId([
                                'code' => $code,
                                'name' => Str::title(str_replace('_', ' ', $code)),
                                'category' => 'other',
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        DB::table('vendor_documents')->where('id', $row->id)->update(['document_type_id' => $typeId]);
                    }
                });
            }

            if (Schema::hasColumn('vendor_documents', 'document_type')) {
                Schema::table('vendor_documents', function (Blueprint $table) {
                    $table->string('document_type')->nullable()->change();
                });
            }
        }

        if (!Schema::hasTable('vendor_document_requirements')) {
            Schema::create('vendor_document_requirements', function (Blueprint $table) {
                $table->id();
                $table->string('vendor_type')->nullable();
                $table->foreignId('document_type_id')->constrained('document_types');
                $table->boolean('is_required')->default(true);
                $table->boolean('is_critical')->default(false);
                $table->boolean('blocks_transaction')->default(false);
                $table->boolean('requires_expiry_date')->default(true);
                $table->integer('warning_days_before_expiry')->default(30);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_document_requirements');
        Schema::dropIfExists('document_types');
    }
};
