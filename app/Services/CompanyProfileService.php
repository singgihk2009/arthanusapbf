<?php
namespace App\Services;

use App\Models\Core\Party;

class CompanyProfileService
{
    public function getDefaultCompanyProfile()
    {
        $party = Party::firstOrCreate(
            ['code' => 'ANP'],
            ['party_type' => 'COMPANY', 'name' => 'ARTHA NUSA PBF', 'status' => 'active']
        );

        return $party->companyProfile()->firstOrCreate([], [
            'legal_name' => $party->name,
            'country' => 'Indonesia',
        ]);
    }
}
