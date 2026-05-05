<?php

namespace App\Services;

use App\Models\CompanyModule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompanyModuleService
{
    public function isEnabled(int $companyId, string $moduleCode): bool
    {
        return CompanyModule::query()
            ->where('company_id', $companyId)
            ->where('module_code', $moduleCode)
            ->where('is_enabled', true)
            ->exists();
    }

    public function getSettings(int $companyId, string $moduleCode): array
    {
        return CompanyModule::query()
            ->where('company_id', $companyId)
            ->where('module_code', $moduleCode)
            ->value('settings_json') ?? [];
    }

    public function requireModule(int $companyId, string $moduleCode): void
    {
        if (!$this->isEnabled($companyId, $moduleCode)) {
            throw new HttpException(403, "Module [{$moduleCode}] is not enabled for this company.");
        }
    }
}
