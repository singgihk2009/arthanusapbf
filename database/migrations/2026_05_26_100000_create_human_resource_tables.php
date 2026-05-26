<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('departments', function (Blueprint $table) {
   $table->id();$table->unsignedBigInteger('company_id')->default(1);$table->string('name');$table->text('description')->nullable();$table->boolean('is_active')->default(true);$table->timestamps();
   $table->unique(['company_id','name']);
  });
  Schema::create('positions', function (Blueprint $table) {
   $table->id();$table->unsignedBigInteger('company_id')->default(1);$table->string('name');$table->string('level')->nullable();$table->text('description')->nullable();$table->boolean('is_active')->default(true);$table->timestamps();$table->unique(['company_id','name']);
  });
  Schema::create('employees', function (Blueprint $table) {
   $table->id();$table->unsignedBigInteger('company_id')->default(1);$table->string('employee_code');$table->string('nik')->nullable();$table->string('full_name');$table->string('gender',20)->nullable();$table->string('birth_place')->nullable();$table->date('birth_date')->nullable();$table->string('phone')->nullable();$table->string('email')->nullable();$table->text('address')->nullable();$table->date('join_date')->nullable();$table->string('employment_status')->nullable();$table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();$table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();$table->unsignedBigInteger('warehouse_id')->nullable();$table->string('photo_path')->nullable();$table->string('signature_path')->nullable();$table->boolean('is_active')->default(true);$table->timestamps();
   $table->unique(['company_id','employee_code']);$table->unique(['company_id','nik']);
  });
  Schema::create('license_types', function (Blueprint $table) {
   $table->id();$table->unsignedBigInteger('company_id')->nullable();$table->string('code');$table->string('name');$table->string('authority')->nullable();$table->boolean('expiry_required')->default(true);$table->boolean('document_required')->default(true);$table->boolean('is_active')->default(true);$table->timestamps();$table->unique(['company_id','code']);
  });
  Schema::create('employee_licenses', function (Blueprint $table) {
   $table->id();$table->unsignedBigInteger('company_id')->default(1);$table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();$table->foreignId('license_type_id')->constrained('license_types')->cascadeOnDelete();$table->string('license_number');$table->string('issued_by')->nullable();$table->date('issued_date')->nullable();$table->date('expired_date')->nullable();$table->enum('status',['active','expiring_soon','expired','suspended','revoked'])->default('active');$table->unsignedBigInteger('document_id')->nullable();$table->string('document_path')->nullable();$table->text('notes')->nullable();$table->boolean('is_primary')->default(false);$table->timestamps();$table->unique(['company_id','license_type_id','license_number']);
  });
  Schema::table('users', function (Blueprint $table) {$table->foreignId('employee_id')->nullable()->after('company_id')->constrained('employees')->nullOnDelete();});
 }
 public function down(): void {
  Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('employee_id'));
  Schema::dropIfExists('employee_licenses');Schema::dropIfExists('license_types');Schema::dropIfExists('employees');Schema::dropIfExists('positions');Schema::dropIfExists('departments');
 }
};
