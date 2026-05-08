<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;

class WarehouseAccessService
{
    public function getAllowedWarehouseIds(User $user): array
    {
        return $user->allowedWarehouseIds();
    }

    public function assertWarehouseAccess(User $user, int|string|null $warehouseId): void
    {
        if ($user->hasRole(['super-admin', 'Admin', 'Super Admin'])) {
            return;
        }

        abort_if(empty($user->allowedWarehouseIds()), 403, 'User belum memiliki akses warehouse. Hubungi administrator.');
        abort_if(! $warehouseId || ! $user->hasWarehouseAccess((int) $warehouseId), 403, 'User tidak memiliki akses ke warehouse ini.');
    }

    public function scopeInventoryQuery(Builder $query, User $user, string $column = 'warehouse_id'): Builder
    {
        if ($user->hasRole(['super-admin', 'Admin', 'Super Admin'])) {
            return $query;
        }

        $allowed = $user->allowedWarehouseIds();
        abort_if(empty($allowed), 403, 'User belum memiliki akses warehouse. Hubungi administrator.');

        return $query->whereIn($column, $allowed);
    }
}
