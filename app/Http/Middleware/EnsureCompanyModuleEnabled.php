<?php

namespace App\Http\Middleware;

use App\Services\CompanyModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyModuleEnabled
{
    public function __construct(private readonly CompanyModuleService $companyModuleService)
    {
    }

    public function handle(Request $request, Closure $next, string $moduleCode): Response
    {
        $companyId = (int) ($request->input('company_id') ?? $request->user()?->company_id ?? 1);
        $this->companyModuleService->requireModule($companyId, $moduleCode);

        return $next($request);
    }
}
