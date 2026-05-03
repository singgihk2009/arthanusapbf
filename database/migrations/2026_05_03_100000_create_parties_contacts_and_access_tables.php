<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('parties')) {
            Schema::create('parties', function (Blueprint $table) {
                $table->id();
                $table->string('party_type')->index();
                $table->string('name');
                $table->string('code')->nullable()->index();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->string('full_name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('mobile')->nullable();
                $table->string('position_title')->nullable();
                $table->string('department')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('party_contacts')) {
            Schema::create('party_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
                $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
                $table->string('contact_role')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('can_login')->default(false);
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['party_id', 'contact_id']);
            });
        }

        if (!Schema::hasTable('party_user_access')) {
            Schema::create('party_user_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
                $table->string('access_role')->nullable();
                $table->boolean('is_default')->default(false);
                $table->string('status')->default('active');
                $table->timestamps();
                $table->unique(['user_id', 'party_id']);
            });
        }

        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'party_id')) {
                $table->foreignId('party_id')->nullable()->after('id')->constrained('parties')->nullOnDelete();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'contact_id')) {
                $table->foreignId('contact_id')->nullable()->after('id')->constrained('contacts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'contact_id')) {
                $table->dropConstrainedForeignId('contact_id');
            }
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'party_id')) {
                $table->dropConstrainedForeignId('party_id');
            }
        });

        Schema::dropIfExists('party_user_access');
        Schema::dropIfExists('party_contacts');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('parties');
    }
};
