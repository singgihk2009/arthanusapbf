<?php
namespace Database\Seeders;

use App\Services\CompanyProfileService;
use Illuminate\Database\Seeder;

class CompanyProfileSeeder extends Seeder
{
    public function run(): void
    {
        app(CompanyProfileService::class)->getDefaultCompanyProfile();
    }
}
