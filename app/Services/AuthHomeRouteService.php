<?php

namespace App\Services;

use App\Models\User;

class AuthHomeRouteService
{
    public function resolve(?User $user): string
    {
        if (! $user) {
            return route('apps.dashboard', absolute: false);
        }

        if ($user->can('dashboard-data')) {
            return route('apps.dashboard', absolute: false);
        }

        if ($user->can('inventory.receiving.view') || $user->can('inventory.view')) {
            return route('apps.inbound.receiving.index', absolute: false);
        }

        if ($user->can('inventory-reports-access')) {
            return route('apps.reports.inventory.index', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }
}
