<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictInventoryReportsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->hasRole('super-admin') || ! $user->hasRole('inventory-reports-access')) {
            return $next($request);
        }

        if (
            $request->routeIs('apps.reports.*')
            || $request->routeIs('apps.procurement.purchase-orders.show')
            || $request->routeIs('apps.document-center.documents.show')
            || $request->routeIs('apps.document-center.documents.download')
        ) {
            return $next($request);
        }

        return redirect()->route('apps.reports.inventory.index')
            ->with('error', 'Anda hanya memiliki akses ke modul report.');
    }
}
