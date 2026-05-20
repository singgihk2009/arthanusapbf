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

            
            'inventory.view',
            'inventory.dashboard.view',
            'inventory.stock_card.view',
            'inventory.stock_ledger.view',
            'inventory.movement.view',
            'inventory.receiving.view',
            'inventory.receiving.create',
            'inventory.receiving.update',
            'inventory.adjustment.view',
            'inventory.adjustment.create',
            'inventory.transfer.view',
            'inventory.transfer.create',
            'inventory.stock_opname.view',
            'inventory.stock_opname.create',
            'inventory.report.view',

            // integration permissions
            'integration-access',
            'integration-outbox-read',
            'integration-outbox-retry',

            // industry module permissions
            'modules.manage',
            'pbf.view',
            'pbf.manage',
            'kek.view',
            'kek.manage',
            'kek.report.view',
            'kek.report.export',
            'facility_document.manage',
            'document.view',
            'document.download',
            'document.verify',
            'document.reject',
            'document.audit.view',
            'setup.company_profile.view',
            'setup.company_profile.update',
            'setup.company_profile.upload_logo',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
