<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // dashboard permissions
            'dashboard-access',

            // users permissions
            'users-access',
            'users-data',
            'users-create',
            'users-update',
            'users-delete',

            // roles permissions
            'roles-access',
            'roles-data',
            'roles-create',
            'roles-update',
            'roles-delete',

            // permissions permissions
            'permissions-access',
            'permissions-data',
            'permissions-create',
            'permissions-update',
            'permissions-delete',


            // inventory master-data permissions
            'master-warehouse-data',
            'master-warehouse-create',
            'master-warehouse-update',
            'master-warehouse-delete',
            'master-category-data',
            'master-category-create',
            'master-category-update',
            'master-category-delete',
            'master-uom-data',
            'master-uom-create',
            'master-uom-update',
            'master-uom-delete',
            'master-item-data',
            'master-item-create',
            'master-item-update',
            'master-item-delete',

            // inventory reports permissions
            'inventory-reports-access',
            'report-stock-balance',
            'report-stock-card',
            'report-expired-soon',
            'report-minimum-stock-alerts',

            // inventory posting permissions
            'inventory-posting-grn',
            'inventory-posting-transfer',
            'inventory-posting-sale',
            'inventory-posting-usage',
            'inventory-posting-adjustment',
            'inventory-posting-opening-balance',

            // integration permissions
            'integration-access',
            'integration-outbox-read',
            'integration-outbox-retry',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
