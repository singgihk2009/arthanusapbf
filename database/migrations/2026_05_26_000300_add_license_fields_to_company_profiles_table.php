<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->string('pbf_license_number', 100)->nullable()->after('country');
            $table->string('idak_license_number', 100)->nullable()->after('pbf_license_number');
            $table->string('cdob_other_license_number', 100)->nullable()->after('idak_license_number');
            $table->string('cdob_ccp_license_number', 100)->nullable()->after('cdob_other_license_number');
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pbf_license_number',
                'idak_license_number',
                'cdob_other_license_number',
                'cdob_ccp_license_number',
            ]);
        });
    }
};
